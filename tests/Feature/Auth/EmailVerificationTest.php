<?php

namespace Tests\Feature\Auth;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->user = User::factory()->unverified()->create(['role' => 'customer']);
        $this->actingAs($this->user);
    }

    private function code(): void
    {
        DB::table('email_verification_codes')->insert(['user_id' => $this->user->id, 'code_hash' => Hash::make('001234'), 'expires_at' => now()->addMinutes(10), 'last_sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_screen_masks_email_and_never_exposes_code(): void
    {
        $this->code();
        $this->get('/verify-email')->assertOk()->assertDontSee('001234')->assertInertia(fn ($p) => $p->component('Customer/Verify')->where('retryAfter', 60));
    }

    public function test_correct_code_accepts_spaces_preserves_zeros_and_is_consumed(): void
    {
        $this->code();
        $this->post('/verify-email', ['code' => '001 234'])->assertRedirect(route('customer.checkout'));
        $this->assertNotNull($this->user->fresh()->email_verified_at);
        $this->assertNotNull(DB::table('email_verification_codes')->first()->consumed_at);
    }

    public function test_wrong_code_counts_five_attempts_and_blocks_correct_code(): void
    {
        $this->code();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-email', ['code' => '999999'])->assertSessionHasErrors('code');
        }$this->assertSame(5, DB::table('email_verification_codes')->first()->attempts);
        $this->post('/verify-email', ['code' => '001234'])->assertSessionHasErrors('code');
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->code();
        $this->travel(10)->minutes();
        $this->post('/verify-email', ['code' => '001234'])->assertSessionHasErrors('code');
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_resend_requires_sixty_seconds_and_invalidates_old_code(): void
    {
        $this->code();
        $this->post('/email/verification-notification')->assertSessionHasErrors('code');
        Mail::assertNothingSent();
        $this->travel(61)->seconds();
        $this->post('/email/verification-notification')->assertRedirect();
        Mail::assertSent(CustomerMessage::class);
        $row = DB::table('email_verification_codes')->first();
        $this->assertFalse(Hash::check('001234', $row->code_hash));
        $this->assertDatabaseCount('email_verification_codes', 1);
        $this->post('/verify-email', ['code' => '001234'])->assertSessionHasErrors('code');
    }

    public function test_consumed_code_cannot_be_used(): void
    {
        $this->code();
        DB::table('email_verification_codes')->update(['consumed_at' => now()]);
        $this->post('/verify-email', ['code' => '001234'])->assertSessionHasErrors('code');
    }

    public function test_signed_link_is_unavailable(): void
    {
        $this->get('/verify-email/'.$this->user->id.'/'.sha1($this->user->email))->assertNotFound();
    }
}
