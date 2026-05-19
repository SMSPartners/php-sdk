# Changelog

All notable changes to the SMS Partners PHP SDK are documented here. This
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
