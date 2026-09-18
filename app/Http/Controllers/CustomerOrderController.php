<?php

namespace App\Http\Controllers;

use App\Mail\CustomerMessage;
use App\Models\User;
use App\Services\CustomerMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerOrderController extends Controller
{
    private function config(): array
    {
        return [...config('madina.public'), 'address' => config('madina.company.address')];
    }

    public function cart()
    {
        return Inertia::render('Customer/Cart', ['publicConfig' => $this->config()]);
    }

    public function checkout(Request $request)
    {
        return Inertia::render('Customer/Checkout', ['customer' => $request->user()->only('name', 'email', 'phone'), 'publicConfig' => $this->config()]);
    }

    private function lines(Request $request): array
    {
        $data = $request->validate(['items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:10000']);
        $lines = [];
        $total = 0;
        foreach (collect($data['items'])->sortBy('id') as $item) {
            $p = DB::table('inventory_products')->where('id', $item['id'])->whereNull('deleted_at')->where('is_published', true)->lockForUpdate()->first();
            if (! $p || ! $p->show_price || $p->sale_price <= 0 || ($p->public_availability_status ?? 'available_now') === 'out_of_stock') {
                throw ValidationException::withMessages(['items' => 'Un produit est indisponible ou nécessite un devis.']);
            }
            if (($p->public_availability_status ?? 'available_now') === 'available_now' && $item['quantity'] > $p->available_quantity) {
                throw ValidationException::withMessages(['items' => 'Stock insuffisant pour '.$p->name.'.']);
            }
            $cents = (int) round((float) $p->sale_price * 100);
            // Keep integer minor-unit arithmetic within JavaScript's exact integer range too.
            if ($cents <= 0 || $cents > intdiv(9007199254740991, (int) $item['quantity']) || $total > 9007199254740991 - $cents * $item['quantity']) {
                throw ValidationException::withMessages(['items' => 'Le montant de ce panier nécessite un devis.']);
            }
            $subtotal = $cents * $item['quantity'];
            $total += $subtotal;
            $lines[] = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->reference, 'quantity' => $item['quantity'], 'unit_price' => $cents / 100, 'subtotal' => $subtotal / 100, 'stock_managed' => ($p->public_availability_status ?? 'available_now') === 'available_now'];
        }

        return ['items' => $lines, 'total' => $total / 100, 'currency' => 'MGA'];
    }

    public function preview(Request $request)
    {
        return DB::transaction(fn () => response()->json($this->lines($request)));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['checkout_key' => 'required|uuid', 'phone' => 'required|string|max:30', 'address' => 'required|string|max:255']);
        $created = false;
        $order = DB::transaction(function () use ($request, $data, &$created) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('orders')->where('checkout_key', $data['checkout_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $request->user()->id, 403);

                return $existing;
            }
            if (! DB::table('payment_accounts')->whereNull('deleted_at')->where('is_active', true)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['payment' => 'La finalisation est temporairement indisponible. Réessayez ultérieurement.']);
            }
            $cart = $this->lines($request);
            $now = now();
            $client = DB::table('clients')->where('user_id', $request->user()->id)->whereNull('deleted_at')->value('id');
            if (! $client) {
                $client = DB::table('clients')->insertGetId(['user_id' => $request->user()->id, 'number' => 'WEB-'.Str::upper(Str::random(20)), 'name' => $request->user()->name, 'contact' => $data['phone'], 'address' => $data['address'], 'type' => 'particulier', 'created_at' => $now, 'updated_at' => $now]);
            } else {
                DB::table('clients')->where('id', $client)->update(['name' => $request->user()->name, 'contact' => $data['phone'], 'address' => $data['address'], 'updated_at' => $now]);
            }
            $id = DB::table('orders')->insertGetId(['number' => 'MI-'.Str::upper(Str::random(24)), 'client_id' => $client, 'manager_id' => $request->user()->id, 'user_id' => $request->user()->id, 'checkout_key' => $data['checkout_key'], 'ordered_at' => $now->toDateString(), 'status' => 'pending_payment', 'payment_status' => 'awaiting_submission', 'client_total' => $cart['total'], 'balance_due' => $cart['total'], 'currency' => 'MGA', 'delivery_snapshot' => json_encode(['name' => $request->user()->name, 'email' => $request->user()->email, ...$data]), 'created_at' => $now, 'updated_at' => $now]);
            foreach ($cart['items'] as $line) {
                $purchasePrice = DB::table('inventory_products')->where('id', $line['id'])->value('purchase_price');
                DB::table('order_items')->insert(['order_id' => $id, 'inventory_product_id' => $line['id'], 'name' => $line['name'], 'sku' => $line['sku'], 'options' => '[]', 'unit_price' => $line['unit_price'], 'web_purchase_price' => $purchasePrice, 'stock_managed' => $line['stock_managed'], 'quantity' => $line['quantity'], 'client_total' => $line['subtotal'], 'status' => 'pending_payment', 'created_at' => $now, 'updated_at' => $now]);
            }
            $created = true;

            return DB::table('orders')->find($id);
        });
        if ($created) {
            app(CustomerMailService::class)->send($request->user(), new CustomerMessage('Commande créée', 'Votre commande est enregistrée. Effectuez le paiement manuel pour la faire confirmer.', orderNumber: $order->number));
        }

        return redirect()->route('customer.orders.show', $order->number)->with('cart_created', $data['checkout_key']);
    }

    private function owned(Request $request, string $number)
    {
        return DB::table('orders')->where('number', $number)->where('user_id', $request->user()->id)->whereNull('deleted_at')->firstOrFail();
    }

    public function index(Request $request)
    {
        return Inertia::render('Customer/Orders', ['orders' => DB::table('orders')->where('user_id', $request->user()->id)->whereNull('deleted_at')->latest('id')->get(['id', 'number', 'ordered_at', 'client_total', 'currency', 'status', 'payment_status']), 'publicConfig' => $this->config()]);
    }

    public function show(Request $request, string $number)
    {
        $order = $this->owned($request, $number);
        $items = $this->imageItems($order->id)->map(function ($item) use ($number) {
            $item->image_url = $this->itemImagePath($item) ? route('customer.orders.image', ['number' => $number, 'item' => $item->id]) : null;
            unset($item->photo_path, $item->product_photo_path);

            return $item;
        });

        return Inertia::render('Customer/Order', ['order' => collect((array) $order)->only(['id', 'number', 'ordered_at', 'created_at', 'client_total', 'currency', 'status', 'payment_status', 'payment_confirmed_at', 'checkout_key', 'delivery_snapshot']), 'items' => $items, 'submissions' => DB::table('payment_submissions')->where('order_id', $order->id)->orderBy('id')->get()->map(function ($s) {
            $s->account_snapshot = json_decode($s->account_snapshot, true);
            $s->has_proof = (bool) $s->proof_path;
            unset($s->proof_path);

            return $s;
        }), 'accounts' => DB::table('payment_accounts')->whereNull('deleted_at')->where('is_active', true)->orderBy('sort_order')->get(['id', 'method', 'display_name', 'account_holder', 'account_number', 'additional_instructions', 'proof_required']), 'cartCreated' => $request->session()->get('cart_created'), 'publicConfig' => $this->config()]);
    }

    private function imageItems(int $orderId)
    {
        return DB::table('order_items')
            ->leftJoin('inventory_products', 'inventory_products.id', '=', 'order_items.inventory_product_id')
            ->where('order_items.order_id', $orderId)
            ->orderBy('order_items.id')
            ->get(['order_items.id', 'order_items.name', 'order_items.sku', 'order_items.options', 'order_items.quantity', 'order_items.unit_price', 'order_items.client_total', 'order_items.photo_path', 'inventory_products.photo_path as product_photo_path']);
    }

    private function itemImagePath(object $item): ?string
    {
        foreach ([$item->photo_path, $item->product_photo_path] as $path) {
            if ($path && Storage::disk('persistent')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function itemImage(Request $request, string $number, int $item)
    {
        $order = $this->owned($request, $number);
        $line = $this->imageItems($order->id)->firstWhere('id', $item);
        abort_unless($line, 404);
        $path = $this->itemImagePath($line);
        abort_unless($path, 404);

        return Storage::disk('persistent')->response($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submit(Request $request, string $number)
    {
        $order = $this->owned($request, $number);
        $data = $request->validate(['submission_key' => 'required|uuid', 'payment_account_id' => 'required|integer', 'transaction_reference' => 'required|string|min:3|max:120', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|max:5120']);
        $created = false;
        DB::transaction(function () use ($request, $order, $data, &$created) {
            $o = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            $existing = DB::table('payment_submissions')->where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->order_id === $o->id, 403);

                return;
            }
            if ($o->status !== 'pending_payment' || ! in_array($o->payment_status, ['awaiting_submission', 'rejected'])) {
                throw ValidationException::withMessages(['payment' => 'Ce paiement ne peut plus être soumis.']);
            }
            $account = DB::table('payment_accounts')->whereNull('deleted_at')->where('id', $data['payment_account_id'])->where('is_active', true)->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['payment_account_id' => 'Moyen de paiement indisponible.']);
            }
            if ($account->proof_required && ! $request->hasFile('proof')) {
                throw ValidationException::withMessages(['proof' => 'Une preuve est requise.']);
            }
            $path = $request->file('proof')?->store('customer-payment-proofs', 'persistent');
            try {
                DB::table('payment_submissions')->insert(['order_id' => $o->id, 'payment_account_id' => $account->id, 'submission_key' => $data['submission_key'], 'account_snapshot' => json_encode(collect((array) $account)->only(['method', 'display_name', 'account_holder', 'account_number'])->all()), 'transaction_reference' => $data['transaction_reference'], 'proof_path' => $path, 'status' => 'under_review', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('orders')->where('id', $o->id)->update(['payment_status' => 'under_review', 'updated_at' => now()]);
                $created = true;
            } catch (\Throwable $e) {
                if ($path) {
                    Storage::disk('persistent')->delete($path);
                } throw $e;
            }
        });
        if ($created) {
            app(CustomerMailService::class)->send($request->user(), new CustomerMessage('Preuve de paiement reçue', 'Votre justificatif a bien été reçu. Madina Import vérifie actuellement votre paiement.', orderNumber: $order->number));
        }

        return back()->with('success', 'Paiement en cours de vérification.');
    }

    public function proof(Request $request, int $id)
    {
        $submission = DB::table('payment_submissions')->find($id);
        abort_unless($submission, 404);
        $order = DB::table('orders')->find($submission->order_id);
        abort_unless($order && ($order->user_id === $request->user()->id || $request->user()->canManageManualPayments()), 403);
        abort_unless($submission->proof_path, 404);

        return Storage::disk('persistent')->download($submission->proof_path, 'justificatif.'.pathinfo($submission->proof_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
