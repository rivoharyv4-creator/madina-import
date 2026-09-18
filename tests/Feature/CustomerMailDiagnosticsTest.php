<?php

namespace Tests\Feature;

use App\Mail\CustomerMessage;
use App\Models\User;
use App\Services\CustomerMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class CustomerMailDiagnosticsTest extends TestCase
{
    public function test_wrapped_provider_error_is_reported_without_mail_content_or_credentials(): void
    {
        config(['mail.default' => 'resend']);
        $provider = new ErrorException(['message' => 'Sensitive content 001234 re_DO_NOT_LOG', 'name' => 'restricted_api_key', 'statusCode' => 403]);
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('Request failed', 0, $provider));
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            $this->assertSame(403, $context['provider_status']);
            $this->assertSame('restricted_api_key', $context['provider_error']);
            $this->assertStringNotContainsString('001234', json_encode($context));
            $this->assertStringNotContainsString('re_DO_NOT_LOG', json_encode($context));

            return $message === 'Customer mail delivery failed';
        });
        $this->assertFalse(app(CustomerMailService::class)->send(new User(['email' => 'test@example.com']), new CustomerMessage('Test', 'Test', '001234')));
    }
}
