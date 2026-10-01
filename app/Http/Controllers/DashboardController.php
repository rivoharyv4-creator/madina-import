<?php

namespace App\Http\Controllers;

use App\Services\MadinaRevenueCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MadinaRevenueCalculator $revenueCalculator)
    {
        $periods = [
            'this_month' => 'Ce mois',
            'last_month' => 'Mois dernier',
            'last_3_months' => '3 derniers mois',
            'last_6_months' => '6 derniers mois',
            'last_12_months' => '12 derniers mois',
            'this_year' => 'Cette année',
        ];
        $period = array_key_exists($request->string('period')->toString(), $periods) ? $request->string('period')->toString() : 'this_month';
        [$start, $end] = match ($period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'last_3_months' => [now()->subMonthsNoOverflow(2)->startOfMonth(), now()->endOfMonth()],
            'last_6_months' => [now()->subMonthsNoOverflow(5)->startOfMonth(), now()->endOfMonth()],
            'last_12_months' => [now()->subMonthsNoOverflow(11)->startOfMonth(), now()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
        $chartStart = in_array($period, ['this_month', 'last_month'], true) ? $start->copy()->subMonthsNoOverflow(5)->startOfMonth() : $start->copy();
        $chartEnd = $end->copy();
        $periodDates = [$start->toDateString(), $end->toDateString()];
        $madinaRevenue = $revenueCalculator->between($start, $end);
        $received = (float) DB::table('client_payments')->whereBetween('paid_at', $periodDates)->where('status', 'valide')->sum('amount');
        $business = (float) DB::table('expenses')->whereBetween('spent_at', $periodDates)->where('type', 'business')->sum('amount');
        $generalBusiness = (float) DB::table('expenses')->whereBetween('spent_at', $periodDates)->where('type', 'business')->whereNull('order_id')->sum('amount');
        $personal = (float) DB::table('expenses')->whereBetween('spent_at', $periodDates)->where('type', 'personnel')->sum('amount');
        $supplier = (float) DB::table('supplier_payments')->whereBetween('paid_at', $periodDates)->sum('amount');
        $commission = (float) DB::table('orders')->whereNull('deleted_at')->whereBetween('ordered_at', $periodDates)->sum('commission_amount');

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {column})"
            : "DATE_FORMAT({column}, '%Y-%m')";
        $monthlyTotals = function (string $table, string $dateColumn, string $amountColumn, ?callable $scope = null) use ($chartStart, $chartEnd, $monthExpression) {
            $expression = str_replace('{column}', $dateColumn, $monthExpression);
            $query = DB::table($table)->whereBetween($dateColumn, [$chartStart->toDateString(), $chartEnd->toDateString()]);
            if ($scope) {
                $scope($query);
            }

            return $query
                ->selectRaw("{$expression} as month_key, SUM({$amountColumn}) as total")
                ->groupByRaw($expression)
                ->pluck('total', 'month_key');
        };

        $monthlyPayments = $monthlyTotals('client_payments', 'paid_at', 'amount', fn ($query) => $query->where('status', 'valide'));
        $monthlyExpenses = $monthlyTotals('expenses', 'spent_at', 'amount');
        $monthlySupplierPayments = $monthlyTotals('supplier_payments', 'paid_at', 'amount');
        $monthCount = (int) $chartStart->copy()->startOfMonth()->diffInMonths($chartEnd->copy()->startOfMonth()) + 1;
        $chart = collect(range(0, $monthCount - 1))->map(function ($i) use ($chartStart, $chartEnd, $revenueCalculator, $monthlyPayments, $monthlyExpenses, $monthlySupplierPayments) {
            $date = $chartStart->copy()->startOfMonth()->addMonths($i);
            $key = $date->format('Y-m');
            $facture = $revenueCalculator->between($date->copy()->max($chartStart), $date->copy()->endOfMonth()->min($chartEnd));
            $encaisse = (float) ($monthlyPayments[$key] ?? 0);
            $depenses = (float) ($monthlyExpenses[$key] ?? 0) + (float) ($monthlySupplierPayments[$key] ?? 0);

            return ['month' => $date->locale('fr')->translatedFormat('M'), 'facture' => $facture, 'encaisse' => $encaisse, 'depenses' => $depenses, 'net' => $encaisse - $depenses];
        })->values();

        $orderStatus = DB::table('orders')->whereNull('deleted_at')->whereBetween('ordered_at', $periodDates)->select('status', DB::raw('count(*) as total'))->groupBy('status')->orderByDesc('total')->get()->map(fn ($row) => ['name' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total])->values();
        $expenseCategories = DB::table('expenses')->whereBetween('spent_at', $periodDates)->select('category', DB::raw('sum(amount) as total'))->groupBy('category')->orderByDesc('total')->get()->map(fn ($row) => ['name' => ucfirst(str_replace('_', ' ', $row->category)), 'value' => (float) $row->total])->values();
        $topProducts = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->whereNull('orders.deleted_at')->whereBetween('orders.ordered_at', $periodDates)->select('order_items.name', DB::raw('sum(order_items.quantity) as quantity'))->groupBy('order_items.name')->orderByDesc('quantity')->limit(6)->get()->map(fn ($row) => ['name' => $row->name, 'quantity' => (float) $row->quantity])->values();

        return Inertia::render('Dashboard', [
            'metrics' => ['invoiced' => $madinaRevenue, 'received' => $received, 'profit' => $madinaRevenue - $generalBusiness, 'cash' => $received - $business - $personal - $supplier, 'commission' => $commission, 'business' => $business, 'personal' => $personal],
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
            'periodFilter' => ['value' => $period, 'label' => $periods[$period], 'chartLabel' => $period === 'this_month' ? '6 derniers mois' : ($period === 'last_month' ? '6 mois jusqu’au mois dernier' : $periods[$period]), 'options' => collect($periods)->map(fn ($label, $value) => compact('value', 'label'))->values()],
        ]);
    }
}
