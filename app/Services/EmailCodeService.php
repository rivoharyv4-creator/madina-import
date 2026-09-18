<?php

namespace App\Services;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailCodeService
{
    public function send(User $user): bool
    {
        $code = DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('email_verification_codes')->where('user_id', $user->id)->first();
            if ($previous && now()->diffInSeconds($previous->last_sent_at, true) < 60) {
                throw ValidationException::withMessages(['code' => 'Patientez 60 secondes avant un nouvel envoi.']);
            }
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('email_verification_codes')->updateOrInsert(['user_id' => $user->id], [
                'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'attempts' => 0,
                'last_sent_at' => now(), 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $code;
        });

        return app(CustomerMailService::class)->send($user, new CustomerMessage('Confirmez votre adresse e-mail', 'Nous avons envoyé un code à six chiffres à votre adresse e-mail.', $code));
    }

    public function verify(User $user, string $input): void
    {
        $error = DB::transaction(function () use ($user, $input) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->email_verified_at) {
                return null;
            }
            $row = DB::table('email_verification_codes')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $row || $row->consumed_at || now()->greaterThanOrEqualTo($row->expires_at)) {
                return 'Code expiré. Demandez un nouveau code.';
            }
            if ($row->attempts >= 5) {
                return 'Nombre maximal de tentatives atteint. Demandez un nouveau code.';
            }
            $code = preg_replace('/\s+/', '', $input);
            if (! preg_match('/^[0-9]{6}$/', $code) || ! Hash::check($code, $row->code_hash)) {
                DB::table('email_verification_codes')->where('id', $row->id)->increment('attempts');

                return 'Code incorrect.';
            }
            $locked->forceFill(['email_verified_at' => now()])->save();
            DB::afterCommit(fn () => event(new Verified($locked)));
            DB::table('email_verification_codes')->where('id', $row->id)->update(['consumed_at' => now()]);

            return null;
        });
        // Throw outside the transaction so incorrect attempt counters are committed.
        if ($error) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }
}
