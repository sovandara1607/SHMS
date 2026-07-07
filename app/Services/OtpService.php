<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

/**
 * One-time-password handling for the forgot/reset-password flow.
 * OTPs are stored in Redis with a short TTL. In a real deployment the
 * code would be emailed; for the demo it is also written to storage/logs.
 */
class OtpService
{
    public function issue(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = (int) env('OTP_TTL', 300);
        Redis::setex($this->key($email), $ttl, Hash::make($code));

        // Demo delivery: log instead of sending an email.
        @file_put_contents(
            storage_path('logs/otp.log'),
            sprintf("[%s] OTP for %s = %s\n", now()->toIso8601String(), $email, $code),
            FILE_APPEND
        );
        return $code;
    }

    public function verify(string $email, string $code): bool
    {
        $hash = Redis::get($this->key($email));
        return $hash !== null && Hash::check($code, $hash);
    }

    public function consume(string $email): void
    {
        Redis::del($this->key($email));
    }

    private function key(string $email): string
    {
        return 'otp:' . strtolower($email);
    }
}
