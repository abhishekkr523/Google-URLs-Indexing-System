<?php

use App\Services\Google\GoogleIndexingService;
use App\Services\Google\ServiceAccountTokenProvider;
use Illuminate\Support\Facades\Http;

/**
 * A disposable RSA key used only to sign test JWTs — unrelated to any real
 * Google account. openssl_pkey_new() cannot generate keys on-the-fly on
 * every environment (it depends on a locally discoverable openssl.cnf), so
 * this fixture is pre-generated once via the openssl CLI instead.
 */
const TEST_FIXTURE_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC2bmZF7FC0EuXm
yADliu8dlJ+gSNlqt91UA0MABWBgt1V4hcfJ1nmu5k61gsJpScFcLdmQknPcpOhg
TtHKp7lNCidzb6dwIQVUz8yefbVXImBTxmfFtX5JwrhHNnkbVZiEF4OfKhGLa+Hp
Gscd1L8yW0Ph07edYHM/g4vbD+t0IOaX7RYnUjSm7uOW3d7WDzsOMsLkHjUxe46x
9PLyMBKea6rf9OJftPxKeCceap5n/eaT+EdXK3l8f4pR4bTnfon6pXxwWoIKCttV
kx9/3ZoxsrXKNZeieJ6wOFK5pEhsIBilRc3VrAGKL28mq8aK+5700ipTWEvryMsy
xXuc4jEhAgMBAAECggEARbZHP7YPmthT4Q4RhaXDPP8Dxpi/+E9ddNKwQixLyXmV
YDJjB6Z9JlAcyLC4gMpt6L8ekefc/XZI1DCaa4IPRbi5HZwPlEISCvhDPVdVOgBJ
Zgn9sqfpo98UUyYmPkNFvkhMBEDrpmbp041nhGc9Ts1gUcX5NtrZ/23RvjrFo0Cf
gwZbKWX7vwTJbcMHKBvKUFLMwmAnqrf9gEje86wwUGE+jZGCL/hntp073HlqYalA
0FP6IhJt6I8ZMrx8FBoSDbdLcd2QC17x6Vw4LBhZ8BL+RnxnHnMEoE0UtQHMQKAX
FkJ31+edVdxN9ucgqyrADCJkvCN6EKnk7CjjMl89rwKBgQDrxq/8Op/kmeAmZr+N
g5A/v0n9rBb4Ba8iQ07aXwgz0FaAwithMlcDBejx2qOIiJhvmwZDI459kYUwNq07
jqQo5GTkFFgsD6fRNZcaAHud+AQIqaAa24qf8KJagzgyntLCh3jl+NWaGoXBbAH1
1ohIZsdt6PIlfDe02A9odL9vjwKBgQDGFFVh2cDXk7wiQ8QWRwBudIfsnlvOkBuF
U1tX+PkAajn0d4w5lL+dScNXc2kNgpUXHODrj3rH3dJmo/DHgAiD/3qRnHHccmVQ
85fZO3DJZBmgsoET43FxGI2AGcY/XxKNJ4LNyC8YOXkySzb3y/Zjyedg8mnjru33
KSP6/SL8TwKBgQCnJfNLUFBcYw46iysPaw7PcpBE60RZTsZK9wam3ypUeUVqAL16
KZLwDLeJBiRbPeM7c96rqEBzGsAeXxTOnSAZ8VjpLNcZXXvuYBygDWmVoudRMNfV
UoDjRFgE7PPhRyFJUXtPJepgVp8ucaCuJQn2sg5+B9/q3TYs2eOKfQHeVwKBgHQO
r+RQUNQ+5GlzKS4gVdlh/84dDw+dkfJxX8DQyRx8IQ7jCM8oH4lKEeFoZrIaw74y
FnsOq1L13nRzM/3AP70PcMJmVrRidoiS4XLPiAsH0pg8XrLHfc1VJHtdHrI8w0Lf
Vp254BYifqeAOGnCINBhGWfNQUu9UAKUIVfK/7ezAoGBAJXIFpveAubOZ2UB9Obr
k+FeWtho2N+3CkQNRVPbYhIXqtGtF1kMRF4IzQHkqdhUgJsPJOm3OcqDKsHUfLmD
xiUpcKdMyg6ILM4JsAm5P94W7du6y/zfmv2nILhHwhW9ZUOIFNWkhdX4Gxw4Y7Qh
JqoVTFzYhVAMN/qBWFoXdzga
-----END PRIVATE KEY-----
PEM;

function makeIndexingServiceWithDisposableCredentials(): GoogleIndexingService
{
    $credentialsPath = tempnam(sys_get_temp_dir(), 'sa_test_').'.json';
    file_put_contents($credentialsPath, json_encode([
        'client_email' => 'test-bot@example-project.iam.gserviceaccount.com',
        'private_key' => TEST_FIXTURE_PRIVATE_KEY,
    ]));

    $tokenProvider = new ServiceAccountTokenProvider(
        credentialsPath: $credentialsPath,
        scope: 'https://www.googleapis.com/auth/indexing',
        tokenUri: 'https://oauth2.googleapis.com/token',
    );

    return new GoogleIndexingService(
        tokenProvider: $tokenProvider,
        publishEndpoint: 'https://indexing.googleapis.com/v3/urlNotifications:publish',
    );
}

test('submit reports success only when Google returns url notification metadata', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'indexing.googleapis.com/*' => Http::response([
            'urlNotificationMetadata' => ['url' => 'https://example.com/'],
        ]),
    ]);

    $result = makeIndexingServiceWithDisposableCredentials()->submit('https://example.com/');

    expect($result['success'])->toBeTrue()
        ->and($result['http_status'])->toBe(200)
        ->and($result['reason'])->toBeNull();
});

test('submit reports failure and preserves the real reason when Google rejects the request', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'indexing.googleapis.com/*' => Http::response([
            'error' => ['code' => 403, 'message' => 'Permission denied.', 'status' => 'PERMISSION_DENIED'],
        ], 403),
    ]);

    $result = makeIndexingServiceWithDisposableCredentials()->submit('https://example.com/');

    expect($result['success'])->toBeFalse()
        ->and($result['http_status'])->toBe(403)
        ->and($result['reason'])->toContain('Permission denied.')
        ->and($result['reason'])->toContain('PERMISSION_DENIED');
});

test('submit never claims success on an unrelated server error', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'indexing.googleapis.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $result = makeIndexingServiceWithDisposableCredentials()->submit('https://example.com/');

    expect($result['success'])->toBeFalse()
        ->and($result['http_status'])->toBe(503);
});

test('submit reports an auth failure without ever calling the indexing endpoint', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response('invalid_grant', 400),
        'indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => []]),
    ]);

    $result = makeIndexingServiceWithDisposableCredentials()->submit('https://example.com/');

    expect($result['success'])->toBeFalse()
        ->and($result['reason'])->toContain('Authentication with Google failed');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'indexing.googleapis.com'));
});
