<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email=(string) env('ADMIN_EMAIL','manager@madina-import.mg');
        $existing=DB::table('users')->where('email',$email)->first();
        $access=[
            'role'=>'super_admin',
            'permissions'=>json_encode(array_keys(config('access.menus',[]))),
            'active'=>true,
            'email_verified_at'=>now(),
            'updated_at'=>now(),
        ];

        if($existing) {
            DB::table('users')->where('id',$existing->id)->update($access);
            return;
        }

        DB::table('users')->insert([
            ...$access,
            'name'=>'Manager Madina',
            'email'=>$email,
            'password'=>filled(env('ADMIN_PASSWORD'))
                ? Hash::make((string) env('ADMIN_PASSWORD'))
                : '$2y$12$.FkyZFO7Qaf6dXQGrBfrteK6yk.Ravf81Y8TR0AmiZVqnZxCEeelq',
            'created_at'=>now(),
        ]);
    }

    public function down(): void
    {
        // The administrator is business data and must not be deleted on rollback.
    }
};
