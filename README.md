# SMS Partners PHP SDK

Official PHP SDK for the [SMS Partners](https://sms-partners.com) API.

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer require sms-partners/php-sdk
```

## Getting Started

Generate an API key from the **Developer → API Tokens** section of your SMS Partners account, then create a client:

```php
use SmsPartners\Client;

$client = new Client(apiKey: 'your-api-key');
```

---

## Sending SMS

```php
$response = $client->send(
    to: '+61412345678',
    message: 'Your verification code is 123456.',
);

echo $response->id;          // Message ID
echo $response->status;      // "sending"
echo $response->to;          // "+61412345678"
echo $response->creditsUsed; // 1
```

### With a custom Sender ID

Pass an approved Sender ID as the `from` argument to control how the message appears to the recipient:

```php
$response = $client->send(
    to: '+61412345678',
    message: 'Your order has shipped.',
    from: 'MYCOMPANY',
);
```

The `from` value must match an **approved** Sender ID on your account. If omitted, the message is sent from the shared number pool.

### Credit cost

Credits are consumed based on message length and character encoding:

| Encoding | Characters per credit |
|---|---|
| GSM-7 (standard ASCII) | 160 |
| Unicode (emoji, non-Latin) | 70 |

A single message can be up to 1,600 characters. Long messages are split into multiple SMS segments and charged accordingly.

---

## Account

Fetch your account details to check your credit balance or account status:

```php
$account = $client->account();

echo $account->name;             // "Acme Corp"
echo $account->email;            // "billing@acme.com"
echo $account->balanceCredits;   // 500
echo $account->status;           // "active"

if ($account->isActive()) {
    // Account is in good standing
}
```

### AccountResponse properties

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Account user ID |
| `name` | `string` | Account holder name |
| `email` | `string` | Account email address |
| `balanceCredits` | `int` | Current credit balance |
| `status` | `string` | `active` or `suspended` |
| `autoTopupEnabled` | `bool` | Whether auto top-up is active |
| `autoTopupThreshold` | `int` | Balance level that triggers top-up |
| `autoTopupAmount` | `int` | Credits purchased on auto top-up |

---

## Webhooks

SMS Partners can send webhook events to your server when a message is delivered or fails. Configure webhook endpoints from the **Developer → Webhooks** section of your account.

### Verifying signatures

Every webhook request includes an `X-Webhook-Signature` header containing an HMAC-SHA256 signature of the raw request body. Always verify this before processing:

```php
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret    = 'your-webhook-secret';

if (! Client::verifyWebhook($payload, $signature, $secret)) {
    http_response_code(401);
    exit;
}
```

### Parsing events

After verifying the signature, parse the payload into a typed event object:

```php
$event = Client::parseWebhook($payload);

echo $event->event;       // "message.delivered"
echo $event->messageId(); // 42
echo $event->recipientPhone(); // "+61412345678"

$event->timestamp; // DateTimeImmutable
```

### Handling specific events

```php
if ($event->isDelivered()) {
    // Message was delivered to the recipient's handset
    $messageId = $event->messageId();
    // Update your records...
}

if ($event->isFailed()) {
    // Delivery failed — check $event->data['recipient']['error_message']
    $error = $event->data['recipient']['error_message'] ?? 'Unknown error';
    // Alert your team...
}
```

### WebhookEvent properties

| Property | Type | Description |
|---|---|---|
| `event` | `string` | Event type: `message.delivered` or `message.failed` |
| `timestamp` | `DateTimeImmutable` | When the event occurred |
| `data` | `array` | Full event payload |

### WebhookEvent methods

| Method | Returns | Description |
|---|---|---|
| `isDelivered()` | `bool` | True if this is a `message.delivered` event |
| `isFailed()` | `bool` | True if this is a `message.failed` event |
| `messageId()` | `?int` | The ID of the message this event relates to |
| `recipientPhone()` | `?string` | The recipient's phone number |

### Webhook payload structure

```json
{
    "event": "message.delivered",
    "timestamp": "2026-04-21T10:00:00.000Z",
    "data": {
        "message_id": 42,
        "recipient": {
            "phone": "+61412345678",
            "status": "delivered",
            "delivered_at": "2026-04-21T10:00:05.000Z",
            "error_message": null
        }
    }
}
```

### Full webhook handler example

```php
<?php

use SmsPartners\Client;
use SmsPartners\Exceptions\SmsPartnersException;

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret    = getenv('SMS_PARTNERS_WEBHOOK_SECRET');

if (! Client::verifyWebhook($payload, $signature, $secret)) {
    http_response_code(401);
    exit;
}

try {
    $event = Client::parseWebhook($payload);
} catch (SmsPartnersException $e) {
    http_response_code(400);
    exit;
}

if ($event->isDelivered()) {
    // handle delivery...
}

if ($event->isFailed()) {
    // handle failure...
}

http_response_code(200);
```

---

## Error Handling

All SDK methods throw exceptions that extend `SmsPartners\Exceptions\SmsPartnersException`. Catch the base class to handle all errors, or catch specific types for fine-grained control:

```php
use SmsPartners\Exceptions\AuthenticationException;
use SmsPartners\Exceptions\InsufficientCreditsException;
use SmsPartners\Exceptions\ValidationException;
use SmsPartners\Exceptions\ApiException;
use SmsPartners\Exceptions\SmsPartnersException;

try {
    $response = $client->send(to: '+61412345678', message: 'Hello!');
} catch (AuthenticationException $e) {
    // Invalid or missing API key
} catch (InsufficientCreditsException $e) {
    // Not enough credits to send
    echo "Balance: {$e->balance}, required: {$e->required}";
} catch (ValidationException $e) {
    // Invalid request data
    foreach ($e->errors as $field => $messages) {
        echo "{$field}: " . implode(', ', $messages);
    }
} catch (ApiException $e) {
    // Unexpected HTTP error
    echo "HTTP {$e->statusCode}: {$e->getMessage()}";
} catch (SmsPartnersException $e) {
    // Connection errors and anything else
}
```

### Exception reference

| Exception | Thrown when |
|---|---|
| `AuthenticationException` | API key is invalid or missing (HTTP 401) |
| `InsufficientCreditsException` | Account does not have enough credits (HTTP 402). Exposes `balance` and `required` properties. |
| `ValidationException` | Request data failed validation (HTTP 422). Exposes an `errors` array keyed by field name. |
| `ApiException` | Unexpected API error. Exposes a `statusCode` property. |
| `SmsPartnersException` | Base class — also thrown for connection failures. |

---

## Configuration

### Custom base URL

If you are using a self-hosted or staging instance, pass a custom base URL as the second argument:

```php
$client = new Client(
    apiKey: 'your-api-key',
    baseUrl: 'https://staging.sms-partners.com',
);
```

---

## Testing

In your test suite, use Guzzle's `MockHandler` to intercept HTTP requests without hitting the real API:

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use SmsPartners\Client;

$mock = new MockHandler([
    new Response(201, [], json_encode([
        'id' => 1,
        'status' => 'sending',
        'to' => '+61412345678',
        'credits_used' => 1,
    ])),
]);

$guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

$client = new Client(apiKey: 'test-key');

// Inject the mock via reflection
$reflection = new ReflectionProperty(Client::class, 'http');
$reflection->setAccessible(true);
$reflection->setValue($client, $guzzle);

$response = $client->send('+61412345678', 'Hello');
```
