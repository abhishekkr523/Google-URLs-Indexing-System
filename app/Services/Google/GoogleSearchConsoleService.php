<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for Google Search Console URL Inspection API v1.
 *
 * This service uses the same service account credentials as the Indexing API
 * but calls a different endpoint: the URL Inspection API. It can:
 *   1. Inspect the current Google indexing status of any URL that belongs to
 *      a Search Console property the service account has access to.
 *   2. Request that Google recrawl a specific URL (equivalent to clicking
 *      "Request Indexing" in Search Console).
 *
 * IMPORTANT LIMITATION (same as Indexing API):
 *   The service account MUST have been added as an "Owner" or at minimum a
 *   "Full User" on the Search Console property. Without that, you will receive
 *   HTTP 403. There is no Google-supported way to request crawling of arbitrary
 *   URLs without owning/verifying the property in Search Console — this is a
 *   hard platform limit, not a gap in this service.
 *
 * NOTE on the /crawl-bridge route elsewhere in this app: exposing arbitrary,
 * unrelated third-party URLs as outbound links purely to bait Googlebot into
 * crawling them is a link-manipulation pattern covered by Google's spam
 * policies (link spam / site reputation abuse), and risks a manual action
 * against this app's own verified domain. It is not a supported or reliable
 * indexing mechanism and should not be relied on or promoted as one.
 */
class GoogleSearchConsoleService
{
    private const INSPECT_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public function __construct(
        private readonly ServiceAccountTokenProvider $tokenProvider,
        private readonly string $siteUrl,
    ) {}

    /**
     * Inspect a URL's current index status via Search Console.
     *
     * @return array{success: bool, http_status: int|null, body: array|null, reason: string|null, coverage_state: string|null}
     */
    public function inspect(string $url): array
    {
        try {
            $accessToken = $this->tokenProvider->getAccessToken();
        } catch (Throwable $e) {
            return $this->authError($e->getMessage());
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post(self::INSPECT_ENDPOINT, [
                    'inspectionUrl' => $url,
                    'siteUrl'       => $this->siteUrl,
                    'languageCode'  => 'en',
                ]);
        } catch (Throwable $e) {
            return $this->networkError($e->getMessage());
        }

        $body = $response->json();
        $body = is_array($body) ? $body : null;
        $success = $response->successful() && isset($body['inspectionResult']);

        $coverageState = $body['inspectionResult']['indexStatusResult']['coverageState'] ?? null;

        return [
            'success'        => $success,
            'http_status'    => $response->status(),
            'body'           => $body,
            'reason'         => $success ? null : $this->extractErrorReason($body, $response->status()),
            'coverage_state' => $coverageState,
        ];
    }

    /**
     * Request Google to recrawl a URL (equivalent to "Request Indexing"
     * button in Search Console). Uses the same Indexing API publish endpoint
     * with URL_UPDATED type — this is the official mechanism.
     *
     * Returns the raw result array from GoogleIndexingService::submit().
     */
    public function requestIndexing(string $url, GoogleIndexingService $indexingService): array
    {
        return $indexingService->submit($url, 'URL_UPDATED');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function authError(string $message): array
    {
        return [
            'success'        => false,
            'http_status'    => null,
            'body'           => null,
            'reason'         => 'Authentication with Google failed: ' . $message,
            'coverage_state' => null,
        ];
    }

    private function networkError(string $message): array
    {
        return [
            'success'        => false,
            'http_status'    => null,
            'body'           => null,
            'reason'         => 'Request to Google Search Console API failed: ' . $message,
            'coverage_state' => null,
        ];
    }

    private function extractErrorReason(?array $body, int $status): string
    {
        if (isset($body['error']['message'])) {
            $reason = $body['error']['message'];

            if (isset($body['error']['status'])) {
                $reason .= ' (' . $body['error']['status'] . ')';
            }

            return $reason;
        }

        return "Google returned an unexpected response (HTTP {$status}).";
    }
}
