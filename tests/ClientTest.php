<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use SmsPartners\Client;
use SmsPartners\Data\AccountResponse;
use SmsPartners\Data\SendResponse;
use SmsPartners\Data\WebhookEvent;
use SmsPartners\Exceptions\ApiException;
use SmsPartners\Exceptions\AuthenticationException;
use SmsPartners\Exceptions\InsufficientCreditsException;
use SmsPartners\Exceptions\MalformedResponseException;
use SmsPartners\Exceptions\SmsPartnersException;
use SmsPartners\Exceptions\ValidationException;

function sendResponseBody(array $overrides = []): string
{
    return json_encode([
        'data' => array_merge([
            'id' => 42,
            'status' => 'sending',
            'body' => 'Hello world',
            'from' => null,
            'scheduled_at' => null,
            'credits_used' => 1,
            'created_at' => '2026-05-19T10:00:00+00:00',
            'recipients' => [[
                'phone' => '+61412345678',
                'status' => 'queued',
                'delivered_at' => null,
                'error_message' => null,
            ]],
        ], $overrides),
    ]);
}

function makeClient(MockHandler $mock, ?array &$history = null): Client
{
    $handler = HandlerStack::create($mock);

    if ($history !== null) {
        $handler->push(Middleware::history($history));
    }

    return new Client(apiKey: 'test-key', httpClient: new GuzzleClient(['handler' => $handler]));
}

it('sends an SMS and returns a SendResponse', function () {
    $mock = new MockHandler([new Response(201, [], sendResponseBody())]);

    $response = makeClient($mock)->send('+61412345678', 'Hello world');

    expect($response)->toBeInstanceOf(SendResponse::class)
        ->and($response->id)->toBe(42)
        ->and($response->status)->toBe('sending')
        ->and($response->to)->toBe('+61412345678')
        ->and($response->creditsUsed)->toBe(1);
});

it('derives the convenience `to` from recipients[0].phone', function () {
    $mock = new MockHandler([new Response(201, [], sendResponseBody([
        'recipients' => [[
            'phone' => '+61499999999',
            'status' => 'queued',
            'delivered_at' => null,
            'error_message' => null,
        ]],
    ]))]);

    expect(makeClient($mock)->send('+61499999999', 'Hi')->to)->toBe('+61499999999');
});

it('includes the from field when provided', function () {
    $mock = new MockHandler([new Response(201, [], sendResponseBody())]);
    $history = [];

    makeClient($mock, $history)->send('+61412345678', 'Hello', 'MYCOMPANY');

    $body = json_decode($history[0]['request']->getBody()->getContents(), true);
    expect($body['from'])->toBe('MYCOMPANY');
});

it('sends a User-Agent identifying the SDK version', function () {
    $mock = new MockHandler([new Response(201, [], sendResponseBody())]);
    $history = [];

    makeClient($mock, $history)->send('+61412345678', 'Hello');

    $ua = $history[0]['request']->getHeaderLine('User-Agent');
    expect($ua)->toStartWith('sms-partners-php/'.Client::VERSION);
});

it('sends requests to an absolute URL under the default base URL', function () {
    $mock = new MockHandler([new Response(201, [], sendResponseBody())]);
    $history = [];

    makeClient($mock, $history)->send('+61412345678', 'Hello');

    expect((string) $history[0]['request']->getUri())->toBe('https://smspartners.app/api/v1/sms')
        ->and($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer test-key');
});

it('honours a custom base URL with a trailing slash', function () {
    $mock = new MockHandler([new Response(200, [], json_encode(['balance' => 7]))]);
    $history = [];
    $handler = HandlerStack::create($mock);
    $handler->push(Middleware::history($history));

    $client = new Client(
        apiKey: 'test-key',
        baseUrl: 'https://staging.example.test/',
        httpClient: new GuzzleClient(['handler' => $handler]),
    );

    expect($client->balance())->toBe(7)
        ->and((string) $history[0]['request']->getUri())->toBe('https://staging.example.test/api/v1/balance');
});

it('throws MalformedResponseException when send response is missing the data envelope', function () {
    $mock = new MockHandler([
        new Response(201, [], json_encode(['id' => 42, 'status' => 'sending'])),
    ]);

    makeClient($mock)->send('+61412345678', 'Hello');
})->throws(MalformedResponseException::class, "missing required field 'data'");

it('throws MalformedResponseException when send response is missing the id', function () {
    $mock = new MockHandler([
        new Response(201, [], json_encode(['data' => [
            'status' => 'sending',
            'created_at' => '2026-05-19T10:00:00+00:00',
        ]])),
    ]);

    makeClient($mock)->send('+61412345678', 'Hello');
})->throws(MalformedResponseException::class, "missing required field 'id'");

it('exposes the missing key on MalformedResponseException', function () {
    $mock = new MockHandler([
        new Response(201, [], json_encode(['data' => [
            'id' => 1,
            'status' => 'sending',
            // no created_at
        ]])),
    ]);

    try {
        makeClient($mock)->send('+61412345678', 'Hello');
    } catch (MalformedResponseException $e) {
        expect($e->missingKey)->toBe('created_at')
            ->and($e->payload)->toHaveKey('id');
    }
});

it('fetches account details', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'balance_credits' => 150,
            'status' => 'active',
            'auto_topup_enabled' => false,
            'auto_topup_threshold' => 25,
            'auto_topup_amount' => 100,
        ])),
    ]);

    $account = makeClient($mock)->account();

    expect($account)->toBeInstanceOf(AccountResponse::class)
        ->and($account->name)->toBe('Test User')
        ->and($account->balanceCredits)->toBe(150)
        ->and($account->isActive())->toBeTrue();
});

it('tolerates optional fields missing from the account payload', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'id' => 1,
            'name' => 'Minimal',
            'email' => 'min@example.com',
            // status, balance_credits, auto_topup_* all missing
        ])),
    ]);

    $account = makeClient($mock)->account();

    expect($account->balanceCredits)->toBe(0)
        ->and($account->status)->toBe('active')
        ->and($account->autoTopupEnabled)->toBeFalse();
});

it('throws MalformedResponseException when account payload is missing the id', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['name' => 'No id'])),
    ]);

    makeClient($mock)->account();
})->throws(MalformedResponseException::class, "missing required field 'id'");

it('throws AuthenticationException on 401', function () {
    $mock = new MockHandler([
        new ClientException(
            'Unauthorized',
            new Request('POST', 'api/v1/sms'),
            new Response(401, [], json_encode(['message' => 'Unauthenticated.'])),
        ),
    ]);

    makeClient($mock)->send('+61412345678', 'Hello');
})->throws(AuthenticationException::class);

it('throws InsufficientCreditsException on 402 with balance details', function () {
    $mock = new MockHandler([
        new ClientException(
            'Payment Required',
            new Request('POST', 'api/v1/sms'),
            new Response(402, [], json_encode([
                'message' => 'Insufficient credits.',
                'balance' => 2,
                'required' => 5,
            ])),
        ),
    ]);

    try {
        makeClient($mock)->send('+61412345678', 'Hello');
    } catch (InsufficientCreditsException $e) {
        expect($e->balance)->toBe(2)
            ->and($e->required)->toBe(5);
    }
});

it('throws ValidationException on 422 with field errors', function () {
    $mock = new MockHandler([
        new ClientException(
            'Unprocessable',
            new Request('POST', 'api/v1/sms'),
            new Response(422, [], json_encode([
                'message' => 'The to field is required.',
                'errors' => ['to' => ['The to field is required.']],
            ])),
        ),
    ]);

    try {
        makeClient($mock)->send('', 'Hello');
    } catch (ValidationException $e) {
        expect($e->errors)->toHaveKey('to');
    }
});

it('throws ApiException with the status code on a 5xx response', function () {
    $mock = new MockHandler([
        new Response(503, [], json_encode(['message' => 'Service unavailable.'])),
    ]);

    try {
        makeClient($mock)->send('+61412345678', 'Hello');
        $this->fail('Expected ApiException');
    } catch (ApiException $e) {
        expect($e->statusCode)->toBe(503)
            ->and($e->getMessage())->toBe('Service unavailable.')
            ->and($e->getPrevious())->toBeInstanceOf(ServerException::class);
    }
});

it('throws ApiException on an unmapped 4xx response', function () {
    $mock = new MockHandler([new Response(429, [], json_encode(['message' => 'Too many requests.']))]);

    try {
        makeClient($mock)->balance();
        $this->fail('Expected ApiException');
    } catch (ApiException $e) {
        expect($e->statusCode)->toBe(429);
    }
});

it('wraps connection failures in SmsPartnersException', function () {
    $mock = new MockHandler([
        new ConnectException(
            'cURL error 28: Connection timed out',
            new Request('POST', 'api/v1/sms'),
        ),
    ]);

    try {
        makeClient($mock)->send('+61412345678', 'Hello');
        $this->fail('Expected SmsPartnersException');
    } catch (SmsPartnersException $e) {
        expect($e->getMessage())->toContain('Could not reach the SMS Partners API')
            ->and($e->getPrevious())->toBeInstanceOf(ConnectException::class);
    }
});

it('wraps any other Guzzle transfer exception in SmsPartnersException', function () {
    // Guzzle 8 throws NetworkTimeoutException / ResponseTimeoutException etc. that
    // are not ConnectExceptions. A bare RequestException is neither a
    // BadResponseException nor a ConnectException on either major, so this
    // proves the GuzzleException catch-all works regardless of Guzzle version.
    $mock = new MockHandler([
        new RequestException(
            'Read timed out',
            new Request('GET', 'api/v1/balance'),
        ),
    ]);

    makeClient($mock)->balance();
})->throws(SmsPartnersException::class, 'Could not reach the SMS Partners API: Read timed out');

it('preserves the Guzzle exception as previous on typed API exceptions', function () {
    $mock = new MockHandler([new Response(401, [], json_encode(['message' => 'Unauthenticated.']))]);

    try {
        makeClient($mock)->account();
        $this->fail('Expected AuthenticationException');
    } catch (AuthenticationException $e) {
        expect($e->getPrevious())->toBeInstanceOf(ClientException::class);
    }
});

it('verifies a valid webhook signature', function () {
    $secret = 'my-secret';
    $payload = '{"event":"message.delivered"}';
    $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

    expect(Client::verifyWebhook($payload, $signature, $secret))->toBeTrue();
});

it('rejects an invalid webhook signature', function () {
    expect(Client::verifyWebhook('payload', 'sha256=invalid', 'secret'))->toBeFalse();
});

it('parses a webhook event payload', function () {
    $payload = json_encode([
        'event' => 'message.delivered',
        'timestamp' => '2026-04-21T10:00:00.000Z',
        'data' => [
            'message_id' => 99,
            'recipient' => [
                'phone' => '+61412345678',
                'status' => 'delivered',
                'delivered_at' => '2026-04-21T10:00:05.000Z',
                'error_message' => null,
            ],
        ],
    ]);

    $event = Client::parseWebhook($payload);

    expect($event)->toBeInstanceOf(WebhookEvent::class)
        ->and($event->isDelivered())->toBeTrue()
        ->and($event->isFailed())->toBeFalse()
        ->and($event->messageId())->toBe(99)
        ->and($event->recipientPhone())->toBe('+61412345678');
});

it('throws MalformedResponseException when webhook payload is missing the event', function () {
    Client::parseWebhook(json_encode(['timestamp' => '2026-04-21T10:00:00Z']));
})->throws(MalformedResponseException::class, "missing required field 'event'");

it('throws MalformedResponseException when webhook payload is missing the timestamp', function () {
    Client::parseWebhook(json_encode(['event' => 'message.delivered']));
})->throws(MalformedResponseException::class, "missing required field 'timestamp'");
