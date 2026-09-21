# Changelog

All notable changes to the SMS Partners PHP SDK are documented here. This
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] — 2026-09-21

### Breaking

- **Requires PHP 8.2+.** PHP 8.1 reached end of life in December 2025 and
  is no longer supported.
- **Requires Guzzle `^7.15.2 || ^8.0.1`.** The previous `^7.0` constraint
  blocked installation alongside Guzzle 8 (released July 2026), which many
  applications adopted to pick up the July 2026 Guzzle security fixes
  (CVE-2026-69246 and related). The new floors are the first patched
  releases on each major, so `composer update` will refuse to leave a
  vulnerable Guzzle in place. Applications still on Guzzle 7 are
  unaffected beyond a patch-level bump.

### Fixed

- **5xx responses, redirect loops and (on Guzzle 8) read timeouts escaped
  as raw Guzzle exceptions**, contradicting the documented guarantee that
  every SDK error extends `SmsPartnersException`. The client previously
  caught only `ClientException` (4xx) and `ConnectException`. It now maps
  every `BadResponseException` (4xx and 5xx) through the typed exception
  hierarchy, and wraps any other `GuzzleException` in
  `SmsPartnersException`. Guzzle 8 splits timeouts into
  `ConnectTimeoutException` / `NetworkTimeoutException` /
  `ResponseTimeoutException`, none of which the old catch handled.

### Added

- `Client::__construct()` accepts an optional third argument,
  `?GuzzleHttp\ClientInterface $httpClient`, for supplying a
  pre-configured Guzzle client (custom timeouts, proxy, CA bundle,
  middleware, or a `MockHandler` in tests). This replaces the
  reflection-based injection previously documented in the README.
  Requests now use absolute URLs, so the supplied client needs no
  `base_uri`.
- Every typed exception now carries the underlying Guzzle exception as
  `getPrevious()`.
- `#[\SensitiveParameter]` on the API key constructor argument, so it is
  redacted from stack traces on PHP 8.2+.
- GitHub Actions CI running the suite on PHP 8.2–8.4 × Guzzle 7 and 8,
  with lowest-dependency jobs and `composer audit` on every run plus a
  weekly schedule.

### Changed

- `vendor/` and `composer.lock` are no longer committed. Distribution
  archives previously bundled a stale vendored copy of Guzzle 7.10.0.
  Dependabot was also unable to resolve the committed lock against the
  declared PHP floor, so it never opened a dependency update.
- Dev dependencies: Pest 3; the unused Mockery dependency is removed.

### Upgrading from 1.x

Most applications need only bump the constraint:

```bash
composer require sms-partners/php-sdk:^2.0
```

Review your error handling if you were catching `GuzzleHttp\Exception\*`
directly around SDK calls — those exceptions are now wrapped, and are
available via `getPrevious()`.

[2.0.0]: https://github.com/SMSPartners/php-sdk/releases/tag/v2.0.0

## [1.0.3] — 2026-05-19

### Fixed

- **Production 500s when an API response field is missing.** Every data
  class previously read `$data['key']` directly into a typed readonly
  property; when the API omitted a field, PHP assigned `null` and raised
  a `TypeError`, surfacing as a 500 in calling apps. Reads now go through
  a defensive `Payload` helper that throws a typed
  `MalformedResponseException` carrying the missing key name and the raw
  payload.
- **`SendResponse::$to` was unreliable.** The API returns `recipients[]`,
  not a flat `to` field. `SendResponse` now derives `$to` from
  `recipients[0]->phone`, with a flat `to` fallback for forward
  compatibility.
- **`Client::send()` and `Client::getMessage()` crashed when the response
  was missing the `data` envelope.** They now throw
  `MalformedResponseException('data', ...)` instead of an opaque
  `TypeError`.
- **`Client::balance()` crashed on a missing `balance` key.** Now throws
  `MalformedResponseException('balance', ...)`.

### Added

- `SmsPartners\Exceptions\MalformedResponseException` — typed exception
  exposing `$missingKey` and `$payload` for debugging. Extends
  `SmsPartnersException`, so existing catch-all handlers still match.
- `SmsPartners\Data\Payload` — internal helper with
  `requireInt` / `requireString` / `requireDateTime` and `optionalString`
  / `optionalInt` / `optionalBool` / `optionalDateTime` /
  `optionalArray` variants.
- `User-Agent: sms-partners-php/<version> php/<phpver>` header on every
  request, so server-side logs can identify SDK versions in the wild.
- `Client::VERSION` constant.
- New test coverage for missing `data`, `id`, `created_at`, webhook
  `event` / `timestamp`, optional-field tolerance on `AccountResponse`,
  `User-Agent` header presence, and `to` derivation from
  `recipients[0]`.

### Changed

- Optional fields (`body`, `from`, `scheduled_at`, `credits_used`,
  `delivered_at`, `error_message`, account `auto_topup_*`) now fall back
  to safe defaults when omitted rather than warning + casting `null`.
- `ClientTest` mocks now match the real API envelope (single resources
  wrapped in `{"data": ...}`); previous mocks tested a flat shape that
  the SDK did not actually parse.

### Compatibility

- **No source-level breaking changes** for callers who consume a
  well-formed API response. The set of public properties on every data
  class is unchanged.
- **Behavioural change:** code paths that previously TypeError'd on a
  malformed payload now throw `MalformedResponseException`. Apps with a
  generic `catch (Throwable)` or `catch (SmsPartnersException)` are
  unaffected; apps catching `TypeError` specifically should switch.
- Requires PHP 8.1+ (unchanged).

[1.0.3]: https://github.com/SMSPartners/php-sdk/releases/tag/v1.0.3
