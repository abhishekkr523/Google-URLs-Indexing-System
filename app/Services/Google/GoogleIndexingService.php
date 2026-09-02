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
 * Known limitation of this mechanism (see README.md): the Indexing API only
 * accepts notifications for URLs on properties the calling service account
 * has been granted "Owner" access to in Google Search Console. Submitting an
 * arbitrary URL on a domain the service account does not own is expected to
 * be rejected by Google with HTTP 403 ("Permission denied"). That rejection
 * is a legitimate, correctly-recorded outcome for this project — not a bug.
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
