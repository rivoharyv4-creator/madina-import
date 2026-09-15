<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorAuthenticationService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl('Madina Import Backoffice', $email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, preg_replace('/\D/', '', $code), 1);
    }

    public function verifyNewer(string $secret, string $code, ?int $lastUsedStep): ?int
    {
        $step = $this->google2fa->verifyKeyNewer(
            $secret,
            preg_replace('/\D/', '', $code),
            $lastUsedStep ?? -1,
            1,
        );

        return $step === false ? null : (int) $step;
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtolower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** @param array<int, string> $codes */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)), $codes);
    }

    /**
     * Consume a recovery code once and return the remaining hashes, or null.
     *
     * @param  array<int, string>  $hashes
     * @return array<int, string>|null
     */
    public function consumeRecoveryCode(array $hashes, string $candidate): ?array
    {
        $candidate = $this->normalizeRecoveryCode($candidate);

        foreach ($hashes as $index => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($code)));
    }
}
