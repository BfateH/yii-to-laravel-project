<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;
use stdClass;

class ExternalJwtValidatorService
{
    private $jwksUrl;
    private $allowedIssuers;
    private $allowedAudiences;

    public function __construct(string $provider)
    {
        $providersConfig = config('sso.providers');

        if (!isset($providersConfig[$provider])) {
            throw new \Exception("Unknown provider: {$provider}");
        }

        $config = $providersConfig[$provider];
        $this->jwksUrl = $config['jwks_url'];
        $this->allowedIssuers = $config['allowed_issuers'];
        $this->allowedAudiences = $config['allowed_audiences'];
    }

    public function validateToken(string $token): stdClass
    {
        // Декодируем заголовок JWT для получения kid
        $tks = explode('.', $token);
        if (count($tks) != 3) {
            throw new \Exception('Wrong number of segments');
        }

        $header = JWT::jsonDecode(JWT::urlsafeB64Decode($tks[0]));

        if (empty($header->kid)) {
            throw new \Exception('JWT header does not contain "kid"');
        }

        if (empty($header->alg)) {
            throw new \Exception('JWT header does not contain "alg"');
        }

        if ($header->alg !== 'RS256') {
            throw new \Exception("Unsupported algorithm: {$header->alg}");
        }

        // Получаем JWKS набор ключей от провайдера
        $jwks = $this->fetchJwks($this->jwksUrl);
        $key = $this->findPublicKey($jwks, $header->kid); // Используем метод для поиска ключа по kid

        // Декодируем JWT
        try {
            $decoded = JWT::decode($token, $key);
        } catch (\Exception $e) {
            Log::error("JWT decoding failed: " . $e->getMessage());
            throw new \Exception("Signature verification failed: " . $e->getMessage());
        }

        return $decoded;
    }

    public function fetchJwks(string $url): array
    {
        $response = Http::timeout(10)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch JWKS from {$url}");
        }

        $jwks = $response->json();

        if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
            throw new \Exception("Invalid JWKS structure: missing 'keys' array");
        }

        return $jwks;
    }

    public function findPublicKey(array $jwks, string $kid): Key
    {
        // Ищем ключ с указанным kid
        foreach ($jwks['keys'] as $key) {
            if ($key['kid'] === $kid) {
                try {
                    $parsedKey = JWK::parseKey($key, 'RS256');
                    return new Key($parsedKey->getKeyMaterial(), 'RS256');
                } catch (\Exception $e) {
                    Log::error("Failed to parse JWK: " . $e->getMessage());
                    throw new \Exception("Invalid key format");
                }
            }
        }

        throw new \Exception("Public key with kid {$kid} not found in JWKS");
    }
}
