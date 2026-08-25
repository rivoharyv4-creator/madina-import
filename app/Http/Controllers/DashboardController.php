<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $month = now()->startOfMonth();
        $safe = fn(string $table, string $column, $query = null) => DB::getSchemaBuilder()->hasTable($table)
            ? (float) (($query ?? DB::table($table))->sum($column)) : 0;
        $invoiced = $safe('invoices','subtotal',DB::table('invoices')->where('issued_at','>=',$month));
        $received = $safe('client_payments','amount',DB::table('client_payments')->where('paid_at','>=',$month)->where('status','valide'));
        $business = $safe('expenses','amount',DB::table('expenses')->where('spent_at','>=',$month)->where('type','business'));
        $personal = $safe('expenses','amount',DB::table('expenses')->where('spent_at','>=',$month)->where('type','personnel'));
        $supplier = $safe('supplier_payments','amount',DB::table('supplier_payments')->where('paid_at','>=',$month));
        $commission = $safe('orders','commission_amount',DB::table('orders')->where('ordered_at','>=',$month));
        $counts = fn($table,$filter=[]) => DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->where($filter)->count() : 0;
        $chart=collect(range(5,0))->map(function($i){
            $start=now()->subMonths($i)->startOfMonth(); $end=$start->copy()->endOfMonth();
            $facture=(float)DB::table('invoices')->whereBetween('issued_at',[$start,$end])->sum('subtotal');
            $encaisse=(float)DB::table('client_payments')->whereBetween('paid_at',[$start,$end])->where('status','valide')->sum('amount');
            $depenses=(float)DB::table('expenses')->whereBetween('spent_at',[$start,$end])->sum('amount')+(float)DB::table('supplier_payments')->whereBetween('paid_at',[$start,$end])->sum('amount');
            return ['month'=>$start->locale('fr')->translatedFormat('M'),'facture'=>$facture,'encaisse'=>$encaisse,'depenses'=>$depenses,'net'=>$encaisse-$depenses];
        })->values();
        $orderStatus=DB::table('orders')->whereNull('deleted_at')->select('status',DB::raw('count(*) as total'))->groupBy('status')->orderByDesc('total')->get()->map(fn($row)=>['name'=>ucfirst(str_replace('_',' ',$row->status)),'value'=>(int)$row->total])->values();
        $expenseCategories=DB::table('expenses')->where('spent_at','>=',$month)->select('category',DB::raw('sum(amount) as total'))->groupBy('category')->orderByDesc('total')->get()->map(fn($row)=>['name'=>ucfirst(str_replace('_',' ',$row->category)),'value'=>(float)$row->total])->values();
        $topProducts=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->whereNull('orders.deleted_at')->select('order_items.name',DB::raw('sum(order_items.quantity) as quantity'))->groupBy('order_items.name')->orderByDesc('quantity')->limit(6)->get()->map(fn($row)=>['name'=>$row->name,'quantity'=>(float)$row->quantity])->values();

        return Inertia::render('Dashboard', [
            'metrics'=>['invoiced'=>$invoiced,'received'=>$received,'profit'=>$received-$business-$personal-$supplier,'cash'=>$received-$business-$personal-$supplier,'commission'=>$commission,'business'=>$business,'personal'=>$personal],
            'counts'=>['orders'=>$counts('orders',[['status','not in',['livre','cloture','annule']]]),'delivered'=>$counts('orders',['status'=>'livre']),'quotes'=>$counts('quotes',['status'=>'envoye']),'shipments'=>$counts('shipments',['status'=>'en_transit']),'lowStock'=>DB::getSchemaBuilder()->hasTable('inventory_products')?DB::table('inventory_products')->whereColumn('quantity','<=','alert_threshold')->count():0],
            'chart'=>$chart,
            'orderStatus'=>$orderStatus,
            'expenseCategories'=>$expenseCategories,
            'topProducts'=>$topProducts,
        ]);
    }
}
