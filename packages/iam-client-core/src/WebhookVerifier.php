<?php

namespace Iam\Client;

final class WebhookVerifier
{
    public static function verify(string $rawBody, string $timestamp, string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }
        $sig = str_starts_with($signatureHeader, 'sha256=') ? substr($signatureHeader, 7) : $signatureHeader;
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $sig);
    }
}
