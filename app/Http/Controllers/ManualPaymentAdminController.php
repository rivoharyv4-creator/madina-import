<?php

namespace App\Http\Controllers;

use App\Mail\CustomerMessage;
use App\Models\User;
use App\Services\CustomerMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ManualPaymentAdminController extends Controller
{
    private function authorizeAdmin(Request $r): void
    {
        abort_unless($r->user()->canManageManualPayments(), 403);
    }

    public function index(Request $r)
    {
        $this->authorizeAdmin($r);
        $authors = DB::table('users')->pluck('name', 'id');

        return Inertia::render('Admin/ManualPayments', ['orders' => DB::table('orders')->whereNotNull('user_id')->whereNull('deleted_at')->latest('id')->get(['id', 'number', 'status', 'payment_status', 'client_total', 'created_at']), 'accounts' => DB::table('payment_accounts')->whereNull('deleted_at')->orderBy('sort_order')->get()->map(function ($account) use ($authors) {
            $account->updated_by_name = $authors[$account->updated_by] ?? 'Utilisateur supprimé';

            return $account;
        }), 'submissions' => DB::table('payment_submissions')->join('orders', 'orders.id', '=', 'payment_submissions.order_id')->join('users', 'users.id', '=', 'orders.user_id')->select('payment_submissions.*', 'orders.number', 'orders.client_total', 'users.name')->latest('payment_submissions.id')->get()->map(function ($s) {
            $s->account_snapshot = json_decode($s->account_snapshot, true);
            $s->has_proof = (bool) $s->proof_path;
            unset($s->proof_path);

            return $s;
        })]);
    }

    public function orderStatus(Request $r, int $id)
    {
        $this->authorizeAdmin($r);
        $data = $r->validate(['status' => 'required|in:processing,completed,cancelled']);
        DB::transaction(function () use ($id, $data) {
            $order = DB::table('orders')->where('id', $id)->whereNotNull('user_id')->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status === $data['status']) {
                return;
            }
            $allowed = ($data['status'] === 'processing' && $order->status === 'confirmed' && $order->payment_status === 'confirmed')
                || ($data['status'] === 'completed' && $order->status === 'processing' && $order->payment_status === 'confirmed')
                || ($data['status'] === 'cancelled' && $order->status === 'pending_payment' && in_array($order->payment_status, ['awaiting_submission', 'rejected']));
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'Transition de commande non autorisée.']);
            }
            DB::table('orders')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
            DB::table('order_items')->where('order_id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
        });

        return back()->with('success', 'Statut de commande enregistré.');
    }

    public function account(Request $r, ?int $id = null)
    {
        $this->authorizeAdmin($r);
        $data = $r->validate(['method' => 'required|string|max:255', 'display_name' => 'required|string|max:255', 'account_holder' => 'required|string|max:255', 'account_number' => 'required|string|max:255', 'additional_instructions' => 'nullable|string|max:3000', 'is_active' => 'required|boolean', 'proof_required' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:10000']);
        if ($id) {
            abort_unless(DB::table('payment_accounts')->whereNull('deleted_at')->find($id), 404);
            DB::table('payment_accounts')->where('id', $id)->update([...$data, 'updated_by' => $r->user()->id, 'updated_at' => now()]);
        } else {
            DB::table('payment_accounts')->insert([...$data, 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('success', 'Compte de paiement enregistré.');
    }

    public function review(Request $r, int $id)
    {
        $this->authorizeAdmin($r);
        $data = $r->validate(['decision' => 'required|in:confirmed,rejected', 'rejection_reason' => 'required_if:decision,rejected|nullable|string|min:5|max:2000']);
        $changed = false;
        $order = DB::transaction(function () use ($r, $id, $data, &$changed) {
            $candidate = DB::table('payment_submissions')->find($id);
            abort_unless($candidate, 404);
            $order = DB::table('orders')->where('id', $candidate->order_id)->lockForUpdate()->first();
            $s = DB::table('payment_submissions')->where('id', $id)->lockForUpdate()->first();
            if ($s->status === $data['decision']) {
                return $order;
            }
            if ($s->status !== 'under_review' || $order->payment_status !== 'under_review' || $order->status !== 'pending_payment') {
                throw ValidationException::withMessages(['payment' => 'Cette soumission a déjà été traitée.']);
            }
            DB::table('payment_submissions')->where('id', $id)->update(['status' => $data['decision'], 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'rejection_reason' => $data['decision'] === 'rejected' ? $data['rejection_reason'] : null, 'updated_at' => now()]);
            $confirmed = $data['decision'] === 'confirmed';
            if ($confirmed) {
                foreach (DB::table('order_items')->where('order_id', $order->id)->where('stock_managed', true)->orderBy('inventory_product_id')->get() as $item) {
                    $product = DB::table('inventory_products')->where('id', $item->inventory_product_id)->whereNull('deleted_at')->lockForUpdate()->first();
                    if (! $product || $product->available_quantity < $item->quantity) {
                        throw ValidationException::withMessages(['payment' => 'Stock insuffisant pour confirmer cette commande. Contactez le client avant de traiter son paiement.']);
                    }
                    $quantity = (float) $product->quantity - (float) $item->quantity;
                    DB::table('inventory_products')->where('id', $product->id)->update(['quantity' => $quantity, 'available_quantity' => max(0, $quantity - (float) $product->reserved_quantity), 'total_purchase_cost' => $quantity * (float) $product->purchase_price + (float) $product->freight, 'sale_total' => $quantity * (float) $product->sale_price, 'stock_value' => $quantity * (float) $product->purchase_price, 'exited_at' => now()->toDateString(), 'updated_at' => now()]);
                    DB::table('stock_movements')->insert(['inventory_product_id' => $product->id, 'type' => 'sortie', 'quantity' => $item->quantity, 'before_quantity' => $product->quantity, 'after_quantity' => $quantity, 'notes' => 'Commande web confirmée '.$order->number, 'moved_at' => now(), 'user_id' => $r->user()->id]);
                }
            }
            DB::table('orders')->where('id', $order->id)->update(['payment_status' => $data['decision'], 'status' => $confirmed ? 'confirmed' : 'pending_payment', 'payment_confirmed_at' => $confirmed ? now() : null, 'deposit' => $confirmed ? $order->client_total : 0, 'balance_due' => $confirmed ? 0 : $order->client_total, 'updated_at' => now()]);
            DB::table('order_items')->where('order_id', $order->id)->update(['status' => $confirmed ? 'confirmed' : 'pending_payment', 'updated_at' => now()]);
            if ($confirmed) {
                DB::table('client_payments')->insert(['client_id' => $order->client_id, 'order_id' => $order->id, 'paid_at' => now()->toDateString(), 'amount' => $order->client_total, 'allocated_amount' => $order->client_total, 'method' => json_decode($s->account_snapshot, true)['method'], 'reference' => $s->transaction_reference, 'type' => 'solde', 'status' => 'valide', 'created_at' => now(), 'updated_at' => now()]);
            }
            $changed = true;

            return $order;
        });
        if ($changed && ($user = User::find($order->user_id))) {
            app(CustomerMailService::class)->send($user, new CustomerMessage($data['decision'] === 'confirmed' ? 'Commande confirmée' : 'Paiement non validé', $data['decision'] === 'confirmed' ? 'Votre paiement est validé et votre commande confirmée.' : $data['rejection_reason'], orderNumber: $order->number));
        }

        return back()->with('success', 'Vérification enregistrée.');
    }

    public function deleteAccount(Request $r, int $id)
    {
        $this->authorizeAdmin($r);
        abort_unless(DB::table('payment_accounts')->whereNull('deleted_at')->find($id), 404);
        DB::table('payment_accounts')->where('id', $id)->update(['is_active' => false, 'deleted_at' => now(), 'updated_at' => now(), 'updated_by' => $r->user()->id]);

        return back()->with('success', 'Compte supprimé. Son historique est conservé.');
    }
}
