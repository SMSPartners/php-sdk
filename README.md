# SMS Partners PHP SDK

Official PHP SDK for the [SMS Partners](https://smspartners.app) API.

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
echo $response->to;          // "+61412345678" (first recipient)
echo $response->body;        // "Your verification code is 123456."
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

### Scheduling a message

Pass a `\DateTimeInterface` as `scheduledAt` to send the message at a future time. Credits are reserved immediately.

```php
$response = $client->send(
    to: '+61412345678',
    message: 'Your appointment is tomorrow at 10am.',
    scheduledAt: new \DateTimeImmutable('+24 hours'),
);

echo $response->status;     // "scheduled"
echo $response->scheduledAt // DateTimeImmutable
```

### Credit cost

Credits are consumed based on message length and character encoding:

| Encoding | Single SMS | Per segment (multipart) |
|---|---|---|
| GSM-7 (standard ASCII) | 160 chars | 153 chars |
| Unicode (emoji, non-Latin) | 70 chars | 67 chars |

A single message can be up to 1,600 characters. Long messages are split into multiple segments and charged per segment.

### SendResponse properties

`SendResponse` extends `Message` and adds a convenience `to` property.

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Message ID |
| `status` | `string` | `sending`, `scheduled`, `sent`, `failed` |
| `body` | `string` | Message body |
| `from` | `?string` | Sender ID name or pool number used |
| `scheduledAt` | `?DateTimeImmutable` | Scheduled send time, or `null` |
| `creditsUsed` | `int` | Credits deducted |
| `createdAt` | `DateTimeImmutable` | When the message was created |
| `recipients` | `Recipient[]` | Delivery status per recipient |
| `to` | `string` | Convenience — phone of the first recipient |

---

## Credit Balance

Quickly check the current balance without fetching the full account object:

```php
$balance = $client->balance();

echo $balance; // 350
```

---

## Messages

### List messages

Retrieve your outbound message history, newest first. Results are paginated at 25 per page.

```php
$page = $client->listMessages();

foreach ($page->data as $message) {
    echo "{$message->id}: {$message->status} — {$message->body}\n";
}

echo $page->total;       // Total messages across all pages
echo $page->currentPage; // 1
echo $page->lastPage;    // 4

if ($page->hasMore()) {
    $next = $client->listMessages(page: 2);
}
```

#### Filter by status

```php
$scheduled = $client->listMessages(status: 'scheduled');
$failed    = $client->listMessages(status: 'failed');
```

Available statuses: `scheduled`, `queued`, `sending`, `sent`, `failed`, `cancelled`.

### Get a single message

```php
$message = $client->getMessage(id: 42);

echo $message->status; // "sent"

foreach ($message->recipients as $recipient) {
    echo "{$recipient->phone}: {$recipient->status}\n";
    echo $recipient->deliveredAt?->format('c'); // DateTimeImmutable or null
}
```

### Message properties

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Message ID |
| `status` | `string` | Current message status |
| `body` | `string` | Message body |
| `from` | `?string` | Sender ID name or pool number used |
| `scheduledAt` | `?DateTimeImmutable` | Scheduled send time, or `null` |
| `creditsUsed` | `int` | Credits charged |
| `createdAt` | `DateTimeImmutable` | When the message was created |
| `recipients` | `Recipient[]` | Delivery status per recipient |

### Recipient properties

| Property | Type | Description |
|---|---|---|
| `phone` | `string` | Recipient phone number |
| `status` | `string` | `queued`, `sent`, `delivered`, or `failed` |
| `deliveredAt` | `?DateTimeImmutable` | Delivery confirmation time, or `null` |
| `errorMessage` | `?string` | Failure reason, or `null` |

### MessagePage properties

| Property | Type | Description |
|---|---|---|
| `data` | `Message[]` | Messages on this page |
| `total` | `int` | Total messages across all pages |
| `perPage` | `int` | Page size (25) |
| `currentPage` | `int` | Current page number |
| `lastPage` | `int` | Last page number |
| `hasMore()` | `bool` | Whether more pages exist |

---

## Sender IDs

List all approved Sender IDs on your account. These are the values you can pass as `from` when sending:

```php
$senderIds = $client->listSenderIds();

foreach ($senderIds as $sender) {
    echo "{$sender->name}\n"; // "MYCOMPANY"
}
```

### SenderId properties

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Sender ID record ID |
| `name` | `string` | The sender name (use this as the `from` value) |
| `status` | `string` | Always `approved` |

---

## Account

Fetch your full account details:

```php
$account = $client->account();

echo $account->name;           // "Acme Corp"
echo $account->email;          // "billing@acme.com"
echo $account->balanceCredits; // 500
echo $account->status;         // "active"

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

echo $event->event;            // "message.delivered"
echo $event->messageId();      // 42
echo $event->recipientPhone(); // "+61412345678"

$event->timestamp; // DateTimeImmutable
```

### Handling specific events

```php
if ($event->isDelivered()) {
    $messageId = $event->messageId();
    // Update your records...
}

if ($event->isFailed()) {
    $error = $event->data['recipient']['error_message'] ?? 'Unknown error';
    // Alert your team...
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

### WebhookEvent reference

| Property / Method | Type | Description |
|---|---|---|
| `event` | `string` | `message.delivered` or `message.failed` |
| `timestamp` | `DateTimeImmutable` | When the event occurred |
| `data` | `array` | Full event payload |
| `isDelivered()` | `bool` | True for `message.delivered` events |
| `isFailed()` | `bool` | True for `message.failed` events |
| `messageId()` | `?int` | ID of the related message |
| `recipientPhone()` | `?string` | Recipient phone number |

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
    // Not enough credits — $e->balance and $e->required are available
    echo "Balance: {$e->balance}, required: {$e->required}";
} catch (ValidationException $e) {
    // Invalid request data — $e->errors is keyed by field name
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
| `InsufficientCreditsException` | Insufficient credits (HTTP 402). Exposes `balance` and `required`. |
| `ValidationException` | Request failed validation (HTTP 422). Exposes `errors` keyed by field name. |
| `ApiException` | Unexpected API error. Exposes `statusCode`. |
| `SmsPartnersException` | Base class — also thrown for connection failures. |

---

## Configuration

### Custom base URL

If you are using a self-hosted or staging instance, pass a custom base URL as the second argument:

```php
$client = new Client(
    apiKey: 'your-api-key',
    baseUrl: 'https://staging.smspartners.app',
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
        'data' => [
            'id' => 1,
            'status' => 'sending',
            'body' => 'Hello!',
            'from' => null,
            'scheduled_at' => null,
            'credits_used' => 1,
            'created_at' => '2026-05-04T05:00:00+00:00',
            'recipients' => [
                ['phone' => '+61412345678', 'status' => 'queued', 'delivered_at' => null, 'error_message' => null],
            ],
        ],
    ])),
]);

$guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

$client = new Client(apiKey: 'test-key');

$reflection = new ReflectionProperty(Client::class, 'http');
$reflection->setAccessible(true);
$reflection->setValue($client, $guzzle);

$response = $client->send('+61412345678', 'Hello!');
assert($response->to === '+61412345678');
assert($response->creditsUsed === 1);
```
