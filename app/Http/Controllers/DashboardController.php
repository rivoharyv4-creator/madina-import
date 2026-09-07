<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $month = now()->startOfMonth();
        $invoiced = (float) DB::table('invoices')->where('issued_at', '>=', $month)->sum('subtotal');
        $received = (float) DB::table('client_payments')->where('paid_at', '>=', $month)->where('status', 'valide')->sum('amount');
        $business = (float) DB::table('expenses')->where('spent_at', '>=', $month)->where('type', 'business')->sum('amount');
        $personal = (float) DB::table('expenses')->where('spent_at', '>=', $month)->where('type', 'personnel')->sum('amount');
        $supplier = (float) DB::table('supplier_payments')->where('paid_at', '>=', $month)->sum('amount');
        $commission = (float) DB::table('orders')->where('ordered_at', '>=', $month)->sum('commission_amount');

        $chartStart = now()->subMonths(5)->startOfMonth();
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {column})"
            : "DATE_FORMAT({column}, '%Y-%m')";
        $monthlyTotals = function (string $table, string $dateColumn, string $amountColumn, ?callable $scope = null) use ($chartStart, $monthExpression) {
            $expression = str_replace('{column}', $dateColumn, $monthExpression);
            $query = DB::table($table)->where($dateColumn, '>=', $chartStart);
            if ($scope) {
                $scope($query);
            }

            return $query
                ->selectRaw("{$expression} as month_key, SUM({$amountColumn}) as total")
                ->groupByRaw($expression)
                ->pluck('total', 'month_key');
        };

        $monthlyInvoices = $monthlyTotals('invoices', 'issued_at', 'subtotal');
        $monthlyPayments = $monthlyTotals('client_payments', 'paid_at', 'amount', fn ($query) => $query->where('status', 'valide'));
        $monthlyExpenses = $monthlyTotals('expenses', 'spent_at', 'amount');
        $monthlySupplierPayments = $monthlyTotals('supplier_payments', 'paid_at', 'amount');
        $chart = collect(range(5, 0))->map(function ($i) use ($monthlyInvoices, $monthlyPayments, $monthlyExpenses, $monthlySupplierPayments) {
            $date = now()->subMonths($i)->startOfMonth();
            $key = $date->format('Y-m');
            $facture = (float) ($monthlyInvoices[$key] ?? 0);
            $encaisse = (float) ($monthlyPayments[$key] ?? 0);
            $depenses = (float) ($monthlyExpenses[$key] ?? 0) + (float) ($monthlySupplierPayments[$key] ?? 0);

            return ['month' => $date->locale('fr')->translatedFormat('M'), 'facture' => $facture, 'encaisse' => $encaisse, 'depenses' => $depenses, 'net' => $encaisse - $depenses];
        })->values();

        $orderStatus = DB::table('orders')->whereNull('deleted_at')->select('status', DB::raw('count(*) as total'))->groupBy('status')->orderByDesc('total')->get()->map(fn ($row) => ['name' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total])->values();
        $expenseCategories = DB::table('expenses')->where('spent_at', '>=', $month)->select('category', DB::raw('sum(amount) as total'))->groupBy('category')->orderByDesc('total')->get()->map(fn ($row) => ['name' => ucfirst(str_replace('_', ' ', $row->category)), 'value' => (float) $row->total])->values();
        $topProducts = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->whereNull('orders.deleted_at')->select('order_items.name', DB::raw('sum(order_items.quantity) as quantity'))->groupBy('order_items.name')->orderByDesc('quantity')->limit(6)->get()->map(fn ($row) => ['name' => $row->name, 'quantity' => (float) $row->quantity])->values();

        return Inertia::render('Dashboard', [
            'metrics' => ['invoiced' => $invoiced, 'received' => $received, 'profit' => $received - $business - $personal - $supplier, 'cash' => $received - $business - $personal - $supplier, 'commission' => $commission, 'business' => $business, 'personal' => $personal],
            'counts' => [
                'orders' => DB::table('orders')->whereNotIn('status', ['livre', 'cloture', 'annule'])->count(),
                'delivered' => DB::table('orders')->where('status', 'livre')->count(),
                'quotes' => DB::table('quotes')->where('status', 'envoye')->count(),
                'shipments' => DB::table('shipments')->where('status', 'en_transit')->count(),
                'lowStock' => DB::table('inventory_products')->whereColumn('quantity', '<=', 'alert_threshold')->count(),
            ],
            'chart' => $chart,
            'orderStatus' => $orderStatus,
            'expenseCategories' => $expenseCategories,
            'topProducts' => $topProducts,
        ]);
    }
}
