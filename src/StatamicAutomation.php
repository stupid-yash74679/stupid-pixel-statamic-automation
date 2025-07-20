<?php

namespace StupidPixel\StatamicAutomation;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class StatamicAutomation
{
    protected $client;
    protected $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => true, // Enable cookie handling for CSRF token
        ]);
    }

    public function getCsrfToken(): ?string
    {
        try {
            $response = $this->client->get('/csrf-token', ['http_errors' => false]);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            error_log("CSRF Token Request Status: " . $statusCode);
            error_log("CSRF Token Request Body: " . $body);

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($body, true);
                return $data['token'] ?? null;
            } else {
                return null;
            }
        } catch (GuzzleException $e) {
            error_log('Error fetching CSRF token: ' . $e->getMessage());
            return null;
        }
    }

    public function createCollection(array $data): array
    {
        return $this->post('/collections', $data);
    }

    public function createBlueprint(array $data): array
    {
        return $this->post('/blueprints', $data);
    }

    public function createAsset(array $data): array
    {
        return $this->post('/assets', $data);
    }

    public function createEntry(array $data): array
    {
        return $this->post('/entries', $data);
    }

    protected function post(string $endpoint, array $data): array
    {
        $csrfToken = $this->getCsrfToken();

        if (!$csrfToken) {
            return ['success' => false, 'message' => 'Could not retrieve CSRF token.'];
        }

        try {
            $response = $this->client->post($endpoint, [
                'json' => $data,
                'headers' => [
                    'X-CSRF-TOKEN' => $csrfToken,
                    'Accept' => 'application/json',
                ],
            ]);

            return [
                'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                'data' => json_decode($response->getBody()->getContents(), true),
                'status_code' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $e) {
            error_log('Error posting to ' . $endpoint . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
}
