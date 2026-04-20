<?php

namespace SmsPartners;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use SmsPartners\Data\AccountResponse;
use SmsPartners\Data\SendResponse;
use SmsPartners\Data\WebhookEvent;
use SmsPartners\Exceptions\ApiException;
use SmsPartners\Exceptions\AuthenticationException;
use SmsPartners\Exceptions\InsufficientCreditsException;
use SmsPartners\Exceptions\SmsPartnersException;
use SmsPartners\Exceptions\ValidationException;

class Client
{
    private GuzzleClient $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://sms-partners.com',
    ) {
        $this->http = new GuzzleClient([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Send an SMS message.
     *
     * @throws SmsPartnersException
     */
    public function send(string $to, string $message, ?string $from = null): SendResponse
    {
        $payload = ['to' => $to, 'message' => $message];

        if ($from !== null) {
            $payload['from'] = $from;
        }

        return new SendResponse($this->request('POST', 'api/v1/sms', $payload));
    }

    /**
     * Get the authenticated account details.
     *
     * @throws SmsPartnersException
     */
    public function account(): AccountResponse
    {
        return new AccountResponse($this->request('GET', 'api/user'));
    }

    /**
     * Verify the HMAC signature of an incoming webhook.
     */
    public static function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Parse a raw webhook payload into a typed event object.
     *
     * @throws SmsPartnersException
     */
    public static function parseWebhook(string $payload): WebhookEvent
    {
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            throw new SmsPartnersException('Invalid webhook payload: could not decode JSON.');
        }

        return new WebhookEvent($data);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws SmsPartnersException
     */
    private function request(string $method, string $uri, array $body = []): array
    {
        try {
            $options = $method !== 'GET' ? ['json' => $body] : [];
            $response = $this->http->request($method, $uri, $options);

            return (array) (json_decode($response->getBody()->getContents(), true) ?? []);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ConnectException $e) {
            throw new SmsPartnersException('Could not connect to the SMS Partners API: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws SmsPartnersException
     */
    private function handleClientException(ClientException $e): never
    {
        $status = $e->getResponse()->getStatusCode();
        $body = (array) (json_decode((string) $e->getResponse()->getBody(), true) ?? []);
        $message = (string) ($body['message'] ?? $e->getMessage());

        throw match ($status) {
            401 => new AuthenticationException($message),
            402 => new InsufficientCreditsException(
                message: $message,
                balance: (int) ($body['balance'] ?? 0),
                required: (int) ($body['required'] ?? 0),
            ),
            422 => new ValidationException(
                message: $message,
                errors: (array) ($body['errors'] ?? []),
            ),
            default => new ApiException($message, $status),
        };
    }
}
