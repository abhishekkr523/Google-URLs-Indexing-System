<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client for the IndexNow protocol.
 *
 * IndexNow is an open standard supported by Bing, Yandex, Seznam.cz and other
 * search engines. It does NOT require a Search Console-style account/property
 * verification flow — but it still requires proof of control over the target
 * host, just via a lighter mechanism than Google:
 *
 *   1. You generate a random API key and host a plain-text file at
 *      https://<host>/<key>.txt containing only that key.
 *   2. You POST URL(s) to submit, along with `host`, `key`, and `keyLocation`.
 *      Per the IndexNow spec, every URL in the request must belong to `host`,
 *      and the receiving engine verifies the key file lives at that same host
 *      before treating the submission as valid.
 *
 * IMPORTANT: this means IndexNow does NOT let you index arbitrary third-party
 * URLs you don't control, any more than Google's Indexing API does. A request
 * whose urlList contains a URL on a different host than the configured `host`
 * is expected to be rejected (spec-documented as HTTP 422 — see parseError()
 * below). This class is only a real "success" for URLs on the host that
 * actually hosts the key file (configured via INDEXNOW_HOST / INDEXNOW_KEY_FILE_URL).
 *
 * Official docs: https://www.indexnow.org/documentation
 *
 * Supported endpoints (any one is enough — they share with each other):
 *   - https://api.indexnow.org/indexnow   (generic, propagates to all)
 *   - https://www.bing.com/indexnow
 *   - https://yandex.com/indexnow
 */
class IndexNowService
{
    /**
     * Primary endpoint — submitting to this one propagates to all
     * IndexNow-participating search engines automatically.
     */
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $keyFileUrl,
        private readonly string $host,
    ) {}

    /**
     * Submit a single URL to IndexNow.
     *
     * @return array{success: bool, http_status: int|null, reason: string|null}
     */
    public function submit(string $url): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'     => false,
                'http_status' => null,
                'reason'      => 'IndexNow API key not configured. Set INDEXNOW_API_KEY in .env.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->post(self::ENDPOINT, [
                    'host'        => $this->host,
                    'key'         => $this->apiKey,
                    'keyLocation' => $this->keyFileUrl,
                    'urlList'     => [$url],
                ]);

            // IndexNow returns 200 or 202 on success
            $success = in_array($response->status(), [200, 202], true);

            if (!$success) {
                Log::warning('IndexNow submission failed', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return [
                'success'     => $success,
                'http_status' => $response->status(),
                'reason'      => $success ? null : $this->parseError($response->status(), $response->body()),
            ];
        } catch (Throwable $e) {
            return [
                'success'     => false,
                'http_status' => null,
                'reason'      => 'IndexNow request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Submit multiple URLs in a single batch request (max 10,000 per call).
     *
     * @param  string[]  $urls
     * @return array{success: bool, http_status: int|null, reason: string|null}
     */
    public function submitBatch(array $urls): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'     => false,
                'http_status' => null,
                'reason'      => 'IndexNow API key not configured. Set INDEXNOW_API_KEY in .env.',
            ];
        }

        if (empty($urls)) {
            return ['success' => true, 'http_status' => 200, 'reason' => null];
        }

        try {
            $response = Http::timeout(15)
                ->post(self::ENDPOINT, [
                    'host'        => $this->host,
                    'key'         => $this->apiKey,
                    'keyLocation' => $this->keyFileUrl,
                    'urlList'     => array_values($urls),
                ]);

            $success = in_array($response->status(), [200, 202], true);

            return [
                'success'     => $success,
                'http_status' => $response->status(),
                'reason'      => $success ? null : $this->parseError($response->status(), $response->body()),
            ];
        } catch (Throwable $e) {
            return [
                'success'     => false,
                'http_status' => null,
                'reason'      => 'IndexNow batch request failed: ' . $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------

    private function parseError(int $status, string $body): string
    {
        return match ($status) {
            400 => 'IndexNow: Invalid request format (HTTP 400).',
            403 => 'IndexNow: Key verification failed — ensure the key file is accessible at ' . $this->keyFileUrl . ' (HTTP 403).',
            422 => 'IndexNow: URL not belonging to the host, or key mismatch (HTTP 422).',
            429 => 'IndexNow: Too many requests — rate limited (HTTP 429).',
            default => "IndexNow: Unexpected response HTTP {$status}. Body: " . substr($body, 0, 200),
        };
    }
}
