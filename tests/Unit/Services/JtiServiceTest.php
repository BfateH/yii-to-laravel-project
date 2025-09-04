<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\JtiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

uses(TestCase::class);

beforeEach(function () {
    $this->jtiService = new JtiService();
    Cache::flush();
});

test('it detects replay attack', function () {
    $jti = 'test-jti-123';
    $provider = 'google';

    // Первая проверка - не использован
    expect($this->jtiService->isAlreadyUsed($jti, $provider))->toBeFalse();

    // Помечаем как использованный
    $this->jtiService->markAsUsed($jti, $provider);

    // Вторая проверка - должен быть использован
    expect($this->jtiService->isAlreadyUsed($jti, $provider))->toBeTrue();
});

test('it generates correct cache key', function () {
    $jti = 'test-jti-123';
    $provider = 'google';

    $key = $this->jtiService->generateKey($jti, $provider);

    expect($key)->toEqual('jti:google:test-jti-123');
});

test('it logs replay attempt', function () {
    Log::shouldReceive('warning')->once()->with('JTI replay attempt detected', [
        'jti' => 'test-jti-123',
        'provider' => 'google',
        'ip' => request()->ip()
    ]);

    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();

    $jti = 'test-jti-123';
    $provider = 'google';

    // Помечаем как использованный
    $this->jtiService->markAsUsed($jti, $provider);

    // Проверяем - должен сработать лог
    $this->jtiService->isAlreadyUsed($jti, $provider);
});
