# Google URL Indexing — Technical Proof of Concept

A Laravel app that submits arbitrary URLs to Google's Indexing API, tracks the
request through a real backend pipeline, and displays the **actual** response
Google returns — never a simulated or manually-assigned result.

## What this is (and isn't)

- Submitting a URL calls Google's real Indexing API using a real Google Cloud
  service account, via a real OAuth2 JWT Bearer token exchange.
- "Submitted" means **Google accepted the API request**. It is not proof the
  page has been crawled or indexed — those are different things, and this app
  never conflates them (see [Result handling](#result-handling)).
- A failure (auth error, permission error, quota error, network error) is
  recorded and shown exactly as received, never hidden or retried into a fake
  success.
- Nothing here fabricates an "Indexed" status. Actual indexing can only be
  confirmed later by a human, manually, in Google Search or Search Console.

## Architecture

| Layer | Responsibility |
|---|---|
| `app/Services/Google/ServiceAccountTokenProvider.php` | Signs a JWT with the service account's private key (RS256, via PHP's `openssl_sign`) and exchanges it for an OAuth2 access token at `https://oauth2.googleapis.com/token`. Caches the token ~50 min. |
| `app/Services/Google/GoogleIndexingService.php` | Calls `POST https://indexing.googleapis.com/v3/urlNotifications:publish` with the bearer token, and returns the **real** HTTP status, raw JSON body, and a parsed failure reason. |
| `app/Jobs/ProcessUrlSubmissionJob.php` | Queued job (`database` queue driver) that does the actual submission in the background and writes the real result back onto the `url_submissions` row. |
| `app/Http/Controllers/UrlSubmissionController.php` | Validates the URL, creates a `pending` record, dispatches the job. Never calls Google synchronously from the request. |
| `app/Http/Controllers/Admin/AdminController.php` | Read-only cross-user view for admins. |

No `google/apiclient` dependency is used — the JWT signing and OAuth2 exchange
are implemented directly against Google's documented HTTP endpoints in about
100 lines, so the entire auth mechanism is auditable in one file instead of
hidden inside a large SDK.

## The Google Indexing mechanism, explained

1. **Auth**: a Google Cloud **service account** (`service_account.json`,
   not committed to git) signs a JWT claiming the scope
   `https://www.googleapis.com/auth/indexing`, and exchanges it for a bearer
   token via the standard [RFC 7523](https://www.rfc-editor.org/rfc/rfc7523) JWT-Bearer flow.
2. **Submission**: the app calls the Indexing API's `urlNotifications:publish`
   endpoint with `{"url": "...", "type": "URL_UPDATED"}`.
3. **What Google can return**:
   - `200` + `urlNotificationMetadata` → the notification was accepted. Stored as `submitted`.
   - `403 PERMISSION_DENIED` → the service account is not a verified owner of
     that URL's property in Search Console (or the Indexing API isn't enabled
     on the Cloud project — see below). Stored as `failed` with Google's exact
     message.
   - Any other 4xx/5xx, or a network/timeout error → stored as `failed` or
     `error` with the real status code/message.
4. **This app never calls or fakes any "verify indexing" step.** Per the
   project's own scope (see [What is intentionally not built](#what-is-intentionally-not-built)),
   actual indexing is confirmed manually later, outside this system.

### Known limitation of the Indexing API — confirmed live, not just documented

Google's Indexing API is officially scoped to pages with `JobPosting` or
`BroadcastEvent` (livestream) structured data, and it **only accepts
notifications for URLs on a property the calling service account has been
added as an Owner of in Search Console.** This isn't just a claim from
Google's docs — it was empirically confirmed by live-testing this app against
real URLs during development, in two stages:

1. Before the Indexing API was enabled on the Cloud project, every
   submission genuinely returned `403 SERVICE_DISABLED`
   ("Web Search Indexing API has not been used in project ... or it is
   disabled").
2. After enabling it, submitting `https://example.com/` (a domain nobody
   running this project owns) genuinely returned a *different* `403`:
   `PERMISSION_DENIED — Permission denied. Failed to verify the URL
   ownership.`

That second result directly answers the requirement doc's own question
("whether \[the mechanism] can be used for arbitrary URLs" — Section 2): **no,
not for URLs on domains you haven't verified**, and the app correctly
captures and displays that real rejection rather than hiding it or faking a
success. That is a correct, honest result for this project, not a bug — the
requirement doc explicitly treats an accurately-recorded failure as an
acceptable outcome (Section 6: ~50%, or even 0%, success is fine as long as
outcomes are genuine and accurately reported).

To get a real `submitted` (200) response instead of a 403 on a given URL, its
domain needs to be verified in **your own** Search Console — the developer
cannot do this on your behalf:

- In [Search Console](https://search.google.com/search-console), add the
  target property (e.g. `https://yourdomain.com/`) and grant
  `indexing-bot@abhishekkr523.iam.gserviceaccount.com` **Owner** access
  under Settings → Users and permissions.

Once that's done, submitting a URL on that verified domain will return a real
`200` with `urlNotificationMetadata`, and the dashboard will show
`Submitted`. URLs on domains you haven't verified will keep returning a real,
correctly-captured `403` — which, per the acceptance criteria above, is
itself a valid demonstrated outcome, not an incomplete one.

## Result handling

Every submission stores the literal HTTP status code and JSON body Google
returned (`url_submissions.http_status_code`, `.response_body`), plus a
human-readable `.failure_reason` when applicable. The detail page
(`/urls/{id}`) shows this raw response verbatim, and explicitly reminds the
viewer that `Submitted` ≠ `Indexed`:

> Google's Indexing API accepted this submission request. This means the
> request was valid and acknowledged — it is not proof that the page has
> been crawled or indexed yet. Actual indexing status must be checked
> manually in Google Search (e.g. a `site:` search) or Search Console,
> typically 10–15 minutes to about an hour later.

## Statuses

`pending` → `processing` → `submitted` | `failed` | `error`

- `pending` — row created, job not yet picked up by the queue worker.
- `processing` — job running, request in flight to Google.
- `submitted` — Google accepted the notification (200 + metadata).
- `failed` — Google rejected the request (4xx/5xx with a parsed reason).
- `error` — an exception on our side (e.g. credentials file missing, network failure) before/without a clean Google response.

## What is intentionally not built

Per the requirement doc's explicit scope (Section 13), this project does
**not** include: automatic Google Search / `site:` verification of real
indexing status, payment/credits/API-key systems, OTP/email
verification/password reset, and no visual-polish/animation work. Actual
indexing status is checked manually, later, outside this app.

## Setup

```bash
composer install
npm install && npm run build   # or `npm run dev` while developing

cp .env.example .env           # already done in this repo; DB_* already points at MySQL
php artisan key:generate       # already done in this repo

# Place your Google Cloud service account key at the project root as
# service_account.json (path is configurable via GOOGLE_INDEXING_CREDENTIALS_PATH).

php artisan migrate
php artisan db:seed            # creates the demo accounts below

php artisan serve
php artisan queue:work         # required — submissions are processed asynchronously
```

### Demo accounts (seeded by `php artisan db:seed`)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Regular user | `demo@example.com` | `password` |

## Testing

```bash
php artisan test
```

`tests/Unit/GoogleIndexingServiceTest.php` exercises the real response-parsing
logic (success/failure/auth-failure/network-failure) against `Http::fake()`
fixtures modeled on Google's actual response shapes — no real network calls,
no real credentials. `tests/Feature/UrlSubmissionTest.php` covers the
submission flow, per-user access control, and admin gating end-to-end against
an in-memory SQLite database.

## Security note

`service_account.json` is a real credential and is git-ignored. If you fork
or redistribute this repo, generate your own service account key — do not
reuse the one used during development of this proof of concept.
