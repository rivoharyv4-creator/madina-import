<?php

namespace App\Services;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerMailService
{
    public function send(User $user, CustomerMessage $mail): bool
    {
        try {
            // Logging mail transports would disclose verification codes and payment details.
            $mailer = config('mail.default');
            $transport = config('mail.mailers.'.$mailer.'.transport');
            if (in_array($transport, ['log', 'failover', 'roundrobin'], true)) {
                throw new \RuntimeException('Unsafe customer mail transport');
            }
            Mail::to($user->email)->send($mail);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Customer mail delivery failed', ['exception_type' => get_class($e)]);

            return false;
        }
    }
}
