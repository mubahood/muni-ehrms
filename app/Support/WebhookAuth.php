<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One place that decides whether a device bridge may post attendance events.
 *
 * Accepts the primary HIKVISION_WEBHOOK_TOKEN plus any extra tokens listed in
 * HIKVISION_WEBHOOK_TOKENS_ACCEPTED (comma separated). Bridges in the field are
 * not always updated when the server token is rotated; listing their token there
 * keeps their punches flowing instead of silently losing them.
 */
class WebhookAuth
{
    /** Every token the server currently accepts. */
    public static function tokens(): array
    {
        $extra = array_map('trim', explode(',', (string) config('services.hikvision.accepted_tokens')));

        return array_values(array_unique(array_filter(
            array_merge([(string) config('services.hikvision.webhook_token')], $extra),
            fn ($t) => $t !== ''
        )));
    }

    /** The token a request presents, whichever way the bridge sends it. */
    public static function presented(Request $request): ?string
    {
        $token = $request->header('X-API-Key')
            ?? $request->header('X-Webhook-Token')
            ?? $request->bearerToken()
            ?? $request->input('token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function accepts(Request $request): bool
    {
        $token = static::presented($request);
        $tokens = static::tokens();

        if (!$tokens) {
            Log::warning('Webhook: no HIKVISION_WEBHOOK_TOKEN configured; all device events are refused');
            return false;
        }

        foreach ($tokens as $expected) {
            if ($token !== null && hash_equals($expected, $token)) {
                return true;
            }
        }

        static::logRefusal($request, $token);
        return false;
    }

    /**
     * Log a refusal at most once per IP and token per hour, with a short
     * fingerprint of the token (never the token itself) so a mismatch can be
     * diagnosed from the log alone.
     */
    protected static function logRefusal(Request $request, ?string $token): void
    {
        $fingerprint = $token === null ? 'none' : substr(hash('sha256', $token), 0, 10);
        $key = 'webhook-refused:' . $request->ip() . ':' . $fingerprint;

        if (Cache::add($key, 1, now()->addHour())) {
            Log::warning('Invalid webhook token attempt', [
                'ip' => $request->ip(),
                'token_fingerprint' => $fingerprint,
                'token_length' => $token === null ? 0 : strlen($token),
                'path' => $request->path(),
                'note' => 'repeats from this source are suppressed for an hour',
            ]);
        }
    }
}
