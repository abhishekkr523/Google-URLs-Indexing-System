<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for Google's Indexing API v3 (urlNotifications:publish).
 *
 * IMPORTANT — this class only ever reports what Google actually returned.
 * It never fabricates a result. In particular:
 *   - success => true means Google's API *accepted the submission request*
 *     (HTTP 200 with an urlNotificationMetadata payload). It does NOT mean
 *     the URL has been crawled or indexed. Actual indexing can only be
 *     confirmed later via Google Search / Search Console.
 *   - success => false covers every other outcome (auth failure, network
 *     failure, or Google rejecting the request) and always carries the
 *     real HTTP status + raw response body + a human-readable reason.
 *
 * Dual-mode indexing:
 *   1. PRIMARY — Google Indexing API (urlNotifications:publish). Works only
 *      for URLs on properties the service account owns in Search Console.
 *   2. FALLBACK (for unknown/non-owned URLs) — The /crawl-bridge route on this
 *      application exposes all submitted URLs as plain HTML links. Googlebot
 *      crawls this app's verified domain and follows those links organically,
 *      which causes the target URLs to be discovered and crawled without
 *      requiring the caller to own those domains.
 *
 * HTTP 403 from Google = the service account is not an owner of that URL's
 * Search Console property. The submission is still recorded in the DB with
 * status=failed and a clear explanation; the URL is also automatically
 * included in /crawl-bridge for organic Googlebot discovery.
 */
class GoogleIndexingService
{
    public function __construct(
        private readonly ServiceAccountTokenProvider $tokenProvider,
        private readonly string $publishEndpoint,
    ) {}

    /**
     * @return array{success: bool, http_status: int|null, body: array|null, reason: string|null}
     */
    public function submit(string $url, string $notificationType = 'URL_UPDATED'): array
    {
        try {
            $accessToken = $this->tokenProvider->getAccessToken();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'http_status' => null,
                'body' => null,
                'reason' => 'Authentication with Google failed: '.$e->getMessage(),
            ];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post($this->publishEndpoint, [
                    'url' => $url,
                    'type' => $notificationType,
                ]);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'http_status' => null,
                'body' => null,
                'reason' => 'Request to Google Indexing API failed: '.$e->getMessage(),
            ];
        }

        $body = $response->json();
        $body = is_array($body) ? $body : null;
        $success = $response->successful() && isset($body['urlNotificationMetadata']);

        return [
            'success' => $success,
            'http_status' => $response->status(),
            'body' => $body,
            'reason' => $success ? null : $this->extractErrorReason($body, $response->status()),
        ];
    }

    private function extractErrorReason(?array $body, int $status): string
    {
        if ($status === 403) {
            $apiMsg = $body['error']['message'] ?? 'Permission denied';
            return "Google rejected this URL (HTTP 403 — {$apiMsg}). "
                . 'The Indexing API only accepts URLs from Search Console properties where '
                . 'your service account has Owner access. This URL has been added to '
                . '/crawl-bridge so Googlebot can discover it organically.';
        }

        if (isset($body['error']['message'])) {
            $reason = $body['error']['message'];

            if (isset($body['error']['status'])) {
                $reason .= ' ('.$body['error']['status'].')';
            }

            return $reason;
        }

        return "Google returned an unexpected response (HTTP {$status}).";
    }
}
