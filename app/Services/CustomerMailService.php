<?php

namespace App\Services;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Exceptions\ErrorException;

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
            $reason = 'transport_failure';
            $providerStatus = null;
            $providerError = null;
            for ($error = $e; $error !== null; $error = $error->getPrevious()) {
                if ($error instanceof ErrorException) {
                    $providerStatus = $error->getErrorCode();
                    $type = $error->getErrorType();
                    $providerError = in_array($type, ['validation_error', 'missing_api_key', 'invalid_api_key', 'restricted_api_key', 'invalid_access', 'rate_limit_exceeded', 'daily_quota_exceeded', 'monthly_quota_exceeded', 'application_error', 'internal_server_error', 'security_error'], true) ? $type : 'other_provider_error';
                }
            }
            for ($error = $e; $error !== null; $error = $error->getPrevious()) {
                $message = strtolower($error->getMessage());
                foreach (['unsafe customer mail transport' => 'unsafe_mailer', 'domain is not verified' => 'sender_domain_not_verified', 'domain is not yet verified' => 'sender_domain_not_verified', 'api key is invalid' => 'invalid_api_key', 'api key is missing' => 'missing_api_key', 'only send testing emails' => 'test_sender_restriction', 'not authorized' => 'sender_not_authorized', 'could not resolve host' => 'dns_failure', 'timed out' => 'network_timeout', 'ssl certificate' => 'tls_certificate_failure', 'too many requests' => 'rate_limit', 'quota' => 'sending_quota', 'unauthorized' => 'invalid_api_key'] as $pattern => $category) {
                    if (str_contains($message, $pattern)) {
                        $reason = $category;
                        break 2;
                    }
                }
            }
            // Fixed categories only: provider messages can contain sensitive mail content.
            Log::error('Customer mail delivery failed', ['exception_type' => get_class($e), 'reason' => $reason, 'provider_status' => $providerStatus, 'provider_error' => $providerError, 'mailer' => config('mail.default'), 'sender' => config('mail.from.address')]);

            return false;
        }
    }
}
