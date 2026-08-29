<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderTrackingController extends Controller
{
    public function regenerate(Request $request, int $id): RedirectResponse
    {
        $order=DB::table('orders')->whereNull('deleted_at')->find($id);
        abort_unless($order,404);
        do {$code=bin2hex(random_bytes(16));} while(DB::table('orders')->where('public_tracking_code',$code)->exists());
        DB::table('orders')->where('id',$id)->update(['public_tracking_code'=>$code,'public_tracking_enabled'=>true,'updated_at'=>now()]);
        DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'event'=>'commandes.suivi_regenere','auditable_type'=>'orders','auditable_id'=>$id,'new_values'=>json_encode(['public_tracking_enabled'=>true]),'ip_address'=>$request->ip(),'created_at'=>now()]);
        return back()->with('success','Un nouveau lien de suivi sécurisé a été généré.');
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $order=DB::table('orders')->whereNull('deleted_at')->find($id);
        abort_unless($order,404);
        $enabled=!$order->public_tracking_enabled;
        if($enabled&&!$order->public_tracking_code) return $this->regenerate($request,$id);
        DB::table('orders')->where('id',$id)->update(['public_tracking_enabled'=>$enabled,'updated_at'=>now()]);
        return back()->with('success',$enabled?'Le suivi public est activé.':'Le suivi public est désactivé.');
    }
}
