<?php

namespace Tests\Feature\Modules\Tracking;

use App\Models\TrackingEvent;
use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\Package;
use App\Modules\Tracking\Contracts\TrackingProviderInterface;
use App\Modules\Tracking\Enums\ErrorTypeEnum;
use App\Modules\Tracking\Exceptions\TrackingException;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\OperationHistoryResponseDTO;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\PostalOrderEventResponseDTO;
use App\Modules\Tracking\Services\TrackingCacheService;
use App\Modules\Tracking\Services\TrackingRateLimitService;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class TrackingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Package $package;
    protected string $validTrackingNumber;
    protected array $validHistoryRecordData;
    protected array $validPostalOrderEventData;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('packages') || !Schema::hasTable('tracking_events') || !Schema::hasTable('tracking_rate_limits')) {
            $this->markTestSkipped('Требуемые таблицы БД (packages, tracking_events, tracking_rate_limits) не найдены. Пропуск теста.');
            return;
        }

        $this->validTrackingNumber = 'RA123456789RU';
        $this->package = Package::create([
            'tracking_number' => $this->validTrackingNumber,
            'status' => PackageStatus::SENT->value,
        ]);

        $this->validHistoryRecordData = [
            'OperationParameters' => [
                'OperDate' => '2023-10-27T10:00:00.000+03:00',
                'OperType' => ['Id' => 2, 'Name' => 'Вручение'],
                'OperAttr' => ['Id' => 1, 'Name' => 'Вручено'],
            ],
            'AddressParameters' => [
                'OperationAddress' => ['Index' => '123456', 'Description' => 'Москва ОПС 123'],
                'DestinationAddress' => ['Index' => '654321', 'Description' => 'Москва'],
                'CountryOper' => ['Id' => 643, 'Code2A' => 'RU', 'Code3A' => 'RUS', 'NameRU' => 'Россия', 'NameEN' => 'Russia'],
            ],
            'ItemParameters' => [
                'Barcode' => $this->validTrackingNumber,
                'Mass' => 1500,
            ],
            'FinanceParameters' => [
                'Payment' => 100000, // 1000.00 руб в копейках
                'Value' => 50000,   // 500.00 руб в копейках
            ],
        ];

        $this->validPostalOrderEventData = [
            'Number' => 'PO12345',
            'EventDateTime' => '2023-10-27T11:00:00.000+03:00',
            'EventType' => 3,
            'EventName' => 'Оплата',
            'IndexTo' => '654321',
            'IndexEvent' => '123456',
            'SumPaymentForward' => 100000, // 1000.00 руб в копейках
            'CountryEventCode' => 'RU',
            'CountryToCode' => 'RU',
        ];
    }

    // --- Тесты для TrackingService ---

    public function test_tracking_service_can_track_package_successfully()
    {
        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');

        $historyDto = new OperationHistoryResponseDTO($this->validHistoryRecordData);
        $postalOrderDto = new PostalOrderEventResponseDTO($this->validPostalOrderEventData);

        $mockProvider->shouldReceive('getTrackingHistory')
            ->with($this->package)
            ->andReturn([$historyDto]);

        $mockProvider->shouldReceive('getPostalOrderEvents')
            ->with($this->package)
            ->andReturn([$postalOrderDto]);

        $rateLimitService = new TrackingRateLimitService();
        $cacheService = new TrackingCacheService();

        $trackingService = new TrackingService($mockProvider, $rateLimitService, $cacheService);

        $result = $trackingService->trackPackage($this->package);

        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('postal_order_events', $result);
        $this->assertArrayHasKey('from_cache', $result);
        $this->assertCount(1, $result['history']);
        $this->assertCount(1, $result['postal_order_events']);
        $this->assertFalse($result['from_cache']);

        $this->assertDatabaseHas('tracking_events', [
            'package_id' => $this->package->id,
            'operation_type_id' => 2,
            'operation_attr_id' => 1,
            'item_barcode' => $this->validTrackingNumber,
        ]);


        $this->package->refresh();
        $this->assertIsArray($this->package->postal_order_events_data);
        $this->assertCount(1, $this->package->postal_order_events_data);
        $this->assertEquals('PO12345', $this->package->postal_order_events_data[0]['number']);
        $this->assertEquals(PackageStatus::RECEIVED, $this->package->status);
        $this->assertNotNull($this->package->last_tracking_update);
        $this->assertNotNull(Cache::get("tracking:last_update:package:{$this->package->id}"));
    }

    public function test_tracking_service_handles_provider_exception()
    {
        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');
        $mockProvider->shouldReceive('getTrackingHistory')
            ->with($this->package)
            ->andThrow(new \Exception("API Error", 500));

        $trackingService = new TrackingService(
            $mockProvider,
            new TrackingRateLimitService(),
            new TrackingCacheService()
        );

        $this->expectException(TrackingException::class);
        $this->expectExceptionMessage('Failed to track package ID');
        $trackingService->trackPackage($this->package);
    }

    public function test_tracking_service_respects_rate_limit()
    {
        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');

        $rateLimitService = new TrackingRateLimitService();

        for ($i = 0; $i < 101; $i++) {
            $rateLimitService->isLimitExceeded('RUSSIAN_POST');
        }

        $trackingService = new TrackingService(
            $mockProvider,
            $rateLimitService,
            new TrackingCacheService()
        );

        $this->expectException(TrackingException::class);
        $this->expectExceptionCode(429);
        $trackingService->trackPackage($this->package);
    }

    public function test_tracking_service_uses_cache_when_not_forced()
    {
        $cacheService = new TrackingCacheService();
        $cachedHistory = [new OperationHistoryResponseDTO($this->validHistoryRecordData)];
        $cachedPostalOrder = [new PostalOrderEventResponseDTO($this->validPostalOrderEventData)];
        $cacheService->putTrackingHistory($this->package, $cachedHistory);
        $cacheService->putPostalOrderEvents($this->package, $cachedPostalOrder);
        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');

        $trackingService = new TrackingService(
            $mockProvider,
            new TrackingRateLimitService(),
            $cacheService
        );

        $result = $trackingService->trackPackage($this->package, false);

        $this->assertTrue($result['from_cache']);
        $this->assertCount(1, $result['history']);
        $this->assertCount(1, $result['postal_order_events']);
    }

    public function test_tracking_service_skips_update_due_to_anti_flood()
    {
        $futureTime = now()->addMinutes(4)->toISOString();
        Cache::put("tracking:last_update:package:{$this->package->id}", $futureTime, now()->addMinutes(6));
        $cacheService = new TrackingCacheService();
        $cachedHistory = [new OperationHistoryResponseDTO($this->validHistoryRecordData)];
        $cachedPostalOrder = [new PostalOrderEventResponseDTO($this->validPostalOrderEventData)];
        $cacheService->putTrackingHistory($this->package, $cachedHistory);
        $cacheService->putPostalOrderEvents($this->package, $cachedPostalOrder);

        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');

        $trackingService = new TrackingService(
            $mockProvider,
            new TrackingRateLimitService(),
            $cacheService
        );

        $result = $trackingService->trackPackage($this->package, false);

        $this->assertTrue($result['from_cache']);
        $this->assertCount(1, $result['history']);
        $this->assertCount(1, $result['postal_order_events']);
    }

    public function test_tracking_service_updates_status_correctly_based_on_last_event()
    {
        $mockProvider = Mockery::mock(TrackingProviderInterface::class);
        $mockProvider->shouldReceive('getIdentifier')->andReturn('RUSSIAN_POST');
        $historyRecordDataSent = $this->validHistoryRecordData;
        $historyRecordDataSent['OperationParameters']['OperType']['Id'] = 1;
        $historyRecordDataSent['OperationParameters']['OperType']['Name'] = 'Прием';
        $historyDtoSent = new OperationHistoryResponseDTO($historyRecordDataSent);

        $mockProvider->shouldReceive('getTrackingHistory')
            ->with($this->package)
            ->andReturn([$historyDtoSent]);

        $mockProvider->shouldReceive('getPostalOrderEvents')
            ->with($this->package)
            ->andReturn([]);

        $trackingService = new TrackingService(
            $mockProvider,
            new TrackingRateLimitService(),
            new TrackingCacheService()
        );

        $this->package->update(['status' => PackageStatus::PAID]);

        $trackingService->trackPackage($this->package);

        $this->package->refresh();
        $this->assertEquals(PackageStatus::SENT, $this->package->status);
    }

    // --- Тесты для TrackingRateLimitService ---

    public function test_rate_limit_service_allows_request_within_limit()
    {
        $service = new TrackingRateLimitService();
        $key = 'test_provider';
        $isExceeded = $service->isLimitExceeded($key, 5, 60);
        $this->assertFalse($isExceeded);
    }

    public function test_rate_limit_service_blocks_request_when_limit_exceeded()
    {
        $service = new TrackingRateLimitService();
        $key = 'test_provider_limited';
        $service->isLimitExceeded($key, 1, 60);
        $isExceeded = $service->isLimitExceeded($key, 1, 60); // должен быть заблокирован
        $this->assertTrue($isExceeded);
    }

    public function test_rate_limit_service_resets_after_window()
    {
        $service = new TrackingRateLimitService();
        $key = 'test_provider_reset';
        $window = 2;
        $service->isLimitExceeded($key, 1, $window);
        $isExceeded1 = $service->isLimitExceeded($key, 1, $window);
        $this->assertTrue($isExceeded1);
        sleep($window + 1);
        $info = $service->getLimitInfo($key, 1, $window);
        DB::table('tracking_rate_limits')->where('key', $service->getDbKey($key))->delete();
        $isExceeded2 = $service->isLimitExceeded($key, 1, $window);
        $this->assertFalse($isExceeded2);
    }

    public function test_rate_limit_service_get_limit_info()
    {
        $service = new TrackingRateLimitService();
        $key = 'test_provider_info';
        $limit = 10;
        $window = 60;

        $infoBefore = $service->getLimitInfo($key, $limit, $window);
        $this->assertEquals(0, $infoBefore['current']);
        $this->assertEquals($limit, $infoBefore['remaining']);

        $service->isLimitExceeded($key, $limit, $window);

        $infoAfter = $service->getLimitInfo($key, $limit, $window);
        $this->assertEquals(1, $infoAfter['current']);
        $this->assertEquals($limit - 1, $infoAfter['remaining']);
        $this->assertArrayHasKey('reset_time', $infoAfter);
        $this->assertArrayHasKey('ttl', $infoAfter);
    }


    // --- Тесты для TrackingCacheService ---

    public function test_cache_service_stores_and_retrieves_data()
    {
        $service = new TrackingCacheService();
        $ttl = 1; // 1 минута

        $dataToCache = [new OperationHistoryResponseDTO($this->validHistoryRecordData)];

        $service->putTrackingHistory($this->package, $dataToCache, $ttl);

        $retrievedData = $service->getTrackingHistory($this->package, $ttl);

        $this->assertNotNull($retrievedData);
        $this->assertCount(1, $retrievedData);
        $this->assertInstanceOf(OperationHistoryResponseDTO::class, $retrievedData[0]);
    }

    public function test_cache_service_returns_null_on_miss()
    {
        $service = new TrackingCacheService();
        $nonExistentPackage = new Package(['id' => 999999]);
        $retrievedData = $service->getTrackingHistory($nonExistentPackage);
        $this->assertNull($retrievedData);
    }

    public function test_cache_service_forgets_data()
    {
        $service = new TrackingCacheService();
        $ttl = 1;

        $dataToCache = [new OperationHistoryResponseDTO($this->validHistoryRecordData)];
        $service->putTrackingHistory($this->package, $dataToCache, $ttl);

        $this->assertNotNull($service->getTrackingHistory($this->package, $ttl));
        $service->forget($this->package);

        $retrievedData = $service->getTrackingHistory($this->package, $ttl);
        $this->assertNull($retrievedData);
    }

    // --- Тесты для DTO ---

    public function test_operation_history_response_dto_constructs_and_parses_correctly()
    {
        $dto = new OperationHistoryResponseDTO($this->validHistoryRecordData);
        $expectedDate = new \DateTimeImmutable('2023-10-27T10:00:00.000+03:00');
        $this->assertEquals($expectedDate, $dto->operationDate);

        $this->assertEquals(2, $dto->operationTypeId);
        $this->assertEquals('Вручение', $dto->operationTypeName);
        $this->assertEquals(1, $dto->operationAttrId);
        $this->assertEquals('Вручено', $dto->operationAttrName);
        $this->assertEquals('123456', $dto->operationAddressIndex);
        $this->assertEquals('Москва ОПС 123', $dto->operationAddressDescription);
        $this->assertEquals('654321', $dto->destinationAddressIndex);
        $this->assertEquals('Москва', $dto->destinationAddressDescription);
        $this->assertEquals(643, $dto->countryOperId);
        $this->assertEquals('RU', $dto->countryOperCode2A);
        $this->assertEquals('RUS', $dto->countryOperCode3A);
        $this->assertEquals('Россия', $dto->countryOperNameRU);
        $this->assertEquals('Russia', $dto->countryOperNameEN);
        $this->assertEquals($this->validTrackingNumber, $dto->itemBarcode);
        $this->assertEquals(1500, $dto->itemMass);
        $this->assertEquals(100000, $dto->payment);
        $this->assertEquals(50000, $dto->value);
        $this->assertEquals($this->validHistoryRecordData, $dto->rawData);

        $attributes = $dto->toTrackingEventAttributes();
        $this->assertEquals($expectedDate, $attributes['operation_date']);
        $this->assertEquals($dto->operationTypeId, $attributes['operation_type_id']);
        $this->assertEquals($dto->operationAddressDescription, $attributes['operation_address_description']);
    }

    public function test_postal_order_event_response_dto_constructs_and_parses_correctly()
    {
        $dto = new PostalOrderEventResponseDTO($this->validPostalOrderEventData);
        $expectedDate = new \DateTimeImmutable('2023-10-27T11:00:00.000+03:00');
        $this->assertEquals($expectedDate, $dto->eventDateTime);

        $this->assertEquals('PO12345', $dto->number);
        $this->assertEquals(3, $dto->eventType);
        $this->assertEquals('Оплата', $dto->eventName);
        $this->assertEquals('654321', $dto->indexTo);
        $this->assertEquals('123456', $dto->indexEvent);
        $this->assertEquals(100000, $dto->sumPaymentForward);
        $this->assertEquals('RU', $dto->countryEventCode);
        $this->assertEquals('RU', $dto->countryToCode);
        $this->assertEquals($this->validPostalOrderEventData, $dto->rawData);

        $array = $dto->toArray();
        $this->assertEquals($expectedDate->format(\DateTimeInterface::ATOM), $array['eventDateTime']); // ISO
        $this->assertEquals($dto->number, $array['number']);
        $this->assertEquals($dto->eventType, $array['eventType']);
    }

    // --- Тесты для моделей и отношений ---

    public function test_tracking_event_belongs_to_package()
    {
        $trackingEvent = TrackingEvent::create([
            'package_id' => $this->package->id,
            'operation_date' => now(),
            'operation_type_id' => 1,
            'operation_type_name' => 'Test Operation',
        ]);

        $retrievedEvent = TrackingEvent::find($trackingEvent->id);

        $relatedPackage = $retrievedEvent->package;
        $this->assertInstanceOf(Package::class, $relatedPackage);
        $this->assertEquals($this->package->id, $relatedPackage->id);
    }

    public function test_package_has_many_tracking_events()
    {
        $event1Data = [
            'package_id' => $this->package->id,
            'operation_date' => now()->subHour(),
            'operation_type_id' => 1,
            'operation_type_name' => 'Test Operation 1',
        ];
        $event2Data = [
            'package_id' => $this->package->id,
            'operation_date' => now(),
            'operation_type_id' => 2,
            'operation_type_name' => 'Test Operation 2',
        ];
        $event1 = TrackingEvent::create($event1Data);
        $event2 = TrackingEvent::create($event2Data);

        $packageWithEvents = Package::with('trackingEvents')->find($this->package->id);

        $this->assertCount(2, $packageWithEvents->trackingEvents);
        $this->assertTrue($packageWithEvents->trackingEvents->contains('id', $event1->id));
        $this->assertTrue($packageWithEvents->trackingEvents->contains('id', $event2->id));
    }

    public function test_package_casts_status_enum()
    {
        $package = Package::create([
            'tracking_number' => 'TEST_CAST_STATUS',
            'status' => PackageStatus::RECEIVED->value
        ]);

        $this->assertInstanceOf(PackageStatus::class, $package->status);
        $this->assertEquals(PackageStatus::RECEIVED, $package->status);
    }

    public function test_package_casts_postal_order_events_data_array()
    {
        $data = [['event' => 'test', 'amount' => 10000]];
        $package = Package::create([
            'tracking_number' => 'TEST_CAST_POE',
            'postal_order_events_data' => $data
        ]);

        $this->assertIsArray($package->postal_order_events_data);
        $this->assertEquals($data, $package->postal_order_events_data);
    }

    public function test_package_casts_last_tracking_error_type_enum()
    {
        $package = Package::create([
            'tracking_number' => 'TEST_CAST_ERROR_TYPE',
            'last_tracking_error_type' => ErrorTypeEnum::NETWORK->value
        ]);

        $this->assertEquals(ErrorTypeEnum::NETWORK->value, $package->last_tracking_error_type);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
