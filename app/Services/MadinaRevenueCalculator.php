<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MadinaRevenueCalculator
{
    public const ANNUAL_TAX_RATE = 5.0;

    /**
     * CA Madina = factures client - achats fournisseur - fret - autres coûts directs.
     *
     * Les coûts d'une commande sont ventilés au prorata lorsqu'elle possède plusieurs
     * factures, afin qu'ils ne soient jamais déduits deux fois.
     */
    public function between(CarbonInterface $start, CarbonInterface $end): float
    {
        $invoiced = $this->fromInvoices(
            DB::table('invoices')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'annulee')
                ->whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])
                ->select('order_id', 'subtotal')
                ->get()
        );
        $webOrders = DB::table('orders')->whereNull('deleted_at')->whereNotNull('user_id')
            ->where('payment_status', 'confirmed')->whereIn('status', ['confirmed', 'processing', 'completed'])
            ->whereBetween('payment_confirmed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNotIn('id', DB::table('invoices')->whereNull('deleted_at')->where('status', '!=', 'annulee')->select('order_id'))
            ->get(['id', 'client_total']);
        $costs = $this->webPurchaseCosts($webOrders->pluck('id'));

        return $invoiced + (float) $webOrders->sum(fn ($order) => (float) $order->client_total - (float) ($costs[$order->id] ?? 0));
    }

    private function webPurchaseCosts(Collection $orderIds): Collection
    {
        $freight = DB::table('orders')->whereIn('id', $orderIds)->pluck('freight', 'id');
        $expenses = DB::table('expenses')->whereIn('order_id', $orderIds)->where('type', 'business')
            ->select('order_id', DB::raw('SUM(amount) as total'))->groupBy('order_id')->pluck('total', 'order_id');

        return DB::table('order_items')->whereIn('order_id', $orderIds)
            ->select('order_id', DB::raw('SUM((COALESCE(web_purchase_price, supplier_price) + china_delivery + packaging) * quantity) as total'), DB::raw('SUM(freight) as item_freight'))
            ->groupBy('order_id')->get()->mapWithKeys(fn ($row) => [$row->order_id => (float) $row->total
                + ((float) ($freight[$row->order_id] ?? 0) > 0 ? (float) $freight[$row->order_id] : (float) $row->item_freight)
                + (float) ($expenses[$row->order_id] ?? 0),
            ]);
    }

    /**
     * Bénéfice annuel = CA Madina - charges générales professionnelles.
     *
     * Les dépenses liées à une commande sont déjà déduites dans le CA Madina.
     */
    public function annualProfit(int $year): float
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();
        $generalExpenses = (float) DB::table('expenses')
            ->where('type', 'business')
            ->whereNull('order_id')
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        return $this->between($start, $end) - $generalExpenses;
    }

    public function annualTax(int $year): float
    {
        return round(max(0, $this->annualProfit($year)) * self::ANNUAL_TAX_RATE / 100, 2);
    }

    /** @param Collection<int, object> $invoices */
    private function fromInvoices(Collection $invoices): float
    {
        if ($invoices->isEmpty()) {
            return 0.0;
        }

        $invoicedByOrder = $invoices->groupBy('order_id')->map(fn (Collection $rows) => (float) $rows->sum('subtotal'));
        $orderIds = $invoicedByOrder->keys();

        $totalInvoicedByOrder = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'annulee')
            ->whereIn('order_id', $orderIds)
            ->select('order_id', DB::raw('SUM(subtotal) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $orders = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('id', $orderIds)
            ->get(['id', 'supplier_total', 'freight', 'user_id', 'payment_status', 'status'])
            ->keyBy('id');
        $webCosts = $this->webPurchaseCosts($orders->filter(fn ($order) => $order->user_id !== null)->keys());

        $otherDirectCosts = DB::table('expenses')
            ->whereIn('order_id', $orderIds)
            ->select('order_id', DB::raw('SUM(amount) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $itemDirectCosts = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->select('order_id', DB::raw('SUM((china_delivery + packaging) * quantity) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        return (float) $invoicedByOrder->sum(function (float $invoiced, int|string $orderId) use ($totalInvoicedByOrder, $orders, $otherDirectCosts, $itemDirectCosts, $webCosts) {
            $order = $orders->get($orderId);
            if (! $order) {
                return $invoiced;
            }

            $totalInvoiced = (float) ($totalInvoicedByOrder[$orderId] ?? 0);
            $share = $totalInvoiced > 0 ? $invoiced / $totalInvoiced : 0;
            if ($order->user_id !== null) {
                if ($order->payment_status !== 'confirmed' || ! in_array($order->status, ['confirmed', 'processing', 'completed'])) {
                    return 0.0;
                }

                return $invoiced - (float) ($webCosts[$orderId] ?? 0) * $share;
            }
            $directCosts = (float) $order->supplier_total
                + (float) $order->freight
                + (float) ($itemDirectCosts[$orderId] ?? 0)
                + (float) ($otherDirectCosts[$orderId] ?? 0);

            return $invoiced - ($directCosts * $share);
        });
    }
}
