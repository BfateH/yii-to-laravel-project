<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ExternalJwtValidatorService;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

test('it throws exception for unknown provider', function () {
    expect(fn() => new ExternalJwtValidatorService('invalid'))
        ->toThrow(\Exception::class, 'Unknown provider: invalid');
});

test('it fetches jwks from url', function () {
    Http::fake([
        'https://example.com/jwks' => Http::response([
            'keys' => [
                [
                    'kid' => '123',
                    'alg' => 'RS256',
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'n' => 'public-key-modulus',
                    'e' => 'AQAB'
                ]
            ]
        ])
    ]);

    // Создаем конфигурацию для тестового провайдера
    config(['sso.providers.test' => [
        'jwks_url' => 'https://example.com/jwks',
        'allowed_issuers' => ['https://test-issuer.com'],
        'allowed_audiences' => ['test-audience']
    ]]);

    $validator = new ExternalJwtValidatorService('test');
    $result = $validator->fetchJwks('https://example.com/jwks');

    expect($result)->toHaveKey('keys')
        ->and($result['keys'])->toHaveCount(1);
});

test('it throws exception for invalid jwks structure', function () {
    Http::fake([
        'https://example.com/jwks' => Http::response(['invalid' => 'data'])
    ]);

    config(['sso.providers.test' => [
        'jwks_url' => 'https://example.com/jwks',
        'allowed_issuers' => ['https://test-issuer.com'],
        'allowed_audiences' => ['test-audience']
    ]]);

    $validator = new ExternalJwtValidatorService('test');

    expect(fn() => $validator->fetchJwks('https://example.com/jwks'))
        ->toThrow(\Exception::class, "Invalid JWKS structure: missing 'keys' array");
});
