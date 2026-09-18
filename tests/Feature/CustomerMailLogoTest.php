<?php

namespace Tests\Feature;

use App\Mail\CustomerMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerMailLogoTest extends TestCase
{
    public function test_logo_is_embedded_in_email_without_remote_image_request(): void
    {
        $mailer = Mail::mailer('array');
        $mailer->to('test@example.com')->send(new CustomerMessage('Confirmation', 'Confirmation', '001234'));
        $email = $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('src="cid:', $email->getHtmlBody());
        $this->assertStringNotContainsString('/brand-logo-transparent', $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $logo = $email->getAttachments()[0];
        $this->assertSame('inline', $logo->getDisposition());
        $this->assertSame('image/png', $logo->getContentType());
        $this->assertStringContainsString('cid:'.$logo->getContentId(), $email->getHtmlBody());
    }
}
