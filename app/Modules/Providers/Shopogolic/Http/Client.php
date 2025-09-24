<?php

namespace App\Modules\Providers\Shopogolic\Http;

use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Client
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected \GuzzleHttp\Client $httpClient;

    /**
     * @var string
     */
    protected mixed $baseUrl;

    /**
     * @var string
     */
    protected mixed $authKey;

    /**
     * @var int
     */
    protected mixed $timeout;

    /**
     * @var bool
     */
    protected mixed $logEnabled;

    /**
     * @var string
     */
    protected mixed $logChannel;

    public function __construct(\GuzzleHttp\Client $httpClient = null)
    {
        $this->baseUrl = config('shopogolic.base_url');
        $this->authKey = config('shopogolic.auth_key');
        $this->timeout = config('shopogolic.timeout', 30);
        $this->logEnabled = config('shopogolic.log_enabled', true);
        $this->logChannel = config('shopogolic.log_channel', 'stack');

        $this->httpClient = $httpClient ?? new \GuzzleHttp\Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => $this->timeout,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->authKey . ':'),
            ],
            'verify' => env('APP_ENV') !== 'local',
        ]);
    }

    /**
     * @param string $uri
     * @param array $query
     * @return array
     * @throws ShopogolicApiException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->sendRequest('GET', $uri, [
            'query' => $query,
        ]);
    }

    /**
     * @param string $uri
     * @param array $data
     * @return array
     * @throws ShopogolicApiException
     */
    public function post(string $uri, array $data = []): array
    {
        return $this->sendRequest('POST', $uri, [
            'json' => $data,
        ]);
    }

    /**
     * @param string $uri
     * @param array $data
     * @return array
     * @throws ShopogolicApiException
     */
    public function put(string $uri, array $data = []): array
    {
        return $this->sendRequest('PUT', $uri, [
            'json' => $data,
        ]);
    }

    /**
     * @param string $uri
     * @return array
     * @throws ShopogolicApiException
     */
    public function delete(string $uri): array
    {
        return $this->sendRequest('DELETE', $uri);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return array
     * @throws ShopogolicApiException
     */
    protected function sendRequest(string $method, string $uri, array $options = []): array
    {
        try {
            if ($this->logEnabled) {
                Log::channel($this->logChannel)->info('Shopogolic API Request', [
                    'method' => $method,
                    'uri'    => $uri,
                    'options' => $options,
                ]);
            }

            $response = $this->httpClient->request($method, $uri, $options);

            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ShopogolicApiException("Invalid JSON response from API: " . json_last_error_msg());
            }

            if ($this->logEnabled) {
                Log::channel($this->logChannel)->info('Shopogolic API Response', [
                    'status' => $response->getStatusCode(),
                    'data'   => $decoded,
                ]);
            }

            return $decoded;

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : null;
            $body = $response ? (string) $response->getBody() : 'No response body';

            if ($this->logEnabled) {
                Log::channel($this->logChannel)->error('Shopogolic API Error', [
                    'method' => $method,
                    'uri'    => $uri,
                    'status' => $statusCode,
                    'error'  => $e->getMessage(),
                    'body'   => $body,
                ]);
            }

            throw new ShopogolicApiException(
                "API request failed: {$e->getMessage()}",
                $statusCode ?? 0,
                $e
            );

        } catch (GuzzleException $e) {
            if ($this->logEnabled) {
                Log::channel($this->logChannel)->error('Shopogolic API Network Error', [
                    'method' => $method,
                    'uri'    => $uri,
                    'error'  => $e->getMessage(),
                ]);
            }

            throw new ShopogolicApiException("Network error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient(): \GuzzleHttp\Client
    {
        return $this->httpClient;
    }
}
