<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MadinaRevenueCalculator
{
    /**
     * CA Madina = factures client - achats fournisseur - fret - autres coûts directs.
     *
     * Les coûts d'une commande sont ventilés au prorata lorsqu'elle possède plusieurs
     * factures, afin qu'ils ne soient jamais déduits deux fois.
     */
    public function between(CarbonInterface $start, CarbonInterface $end): float
    {
        return $this->fromInvoices(
            DB::table('invoices')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'annulee')
                ->whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])
                ->select('order_id', 'subtotal')
                ->get()
        );
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
            ->get(['id', 'supplier_total', 'freight'])
            ->keyBy('id');

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

        return (float) $invoicedByOrder->sum(function (float $invoiced, int|string $orderId) use ($totalInvoicedByOrder, $orders, $otherDirectCosts, $itemDirectCosts) {
            $order = $orders->get($orderId);
            if (! $order) {
                return $invoiced;
            }

            $totalInvoiced = (float) ($totalInvoicedByOrder[$orderId] ?? 0);
            $share = $totalInvoiced > 0 ? $invoiced / $totalInvoiced : 0;
            $directCosts = (float) $order->supplier_total
                + (float) $order->freight
                + (float) ($itemDirectCosts[$orderId] ?? 0)
                + (float) ($otherDirectCosts[$orderId] ?? 0);

            return $invoiced - ($directCosts * $share);
        });
    }
}
