<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class NumberSequenceService
{
    public function next(string $type, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = ['quote' => 'DV-MI', 'order' => 'MI', 'invoice' => 'FA-MI', 'fee_invoice' => 'FF-MI', 'product' => 'PRD', 'client' => 'CLI'][$type] ?? strtoupper($type);

        return DB::transaction(function () use ($type, $year, $prefix) {
            $row = DB::table('number_sequences')->where(compact('type', 'year'))->lockForUpdate()->first();
            if (!$row) {
                DB::table('number_sequences')->insert(['type'=>$type,'year'=>$year,'last_number'=>1,'created_at'=>now(),'updated_at'=>now()]);
                $next = 1;
            } else {
                $next = $row->last_number + 1;
                DB::table('number_sequences')->where('id',$row->id)->update(['last_number'=>$next,'updated_at'=>now()]);
            }
            return $type === 'product' ? sprintf('%s-%03d',$prefix,$next) : sprintf('%s-%d-%03d',$prefix,$year,$next);
        }, 3);
    }
}
