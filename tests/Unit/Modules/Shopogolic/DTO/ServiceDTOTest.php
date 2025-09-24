<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ServiceDTO;
use App\Modules\Providers\Shopogolic\DTO\ServiceTypeDTO;
use Tests\TestCase;

class ServiceDTOTest extends TestCase
{
    public function test_service_dto_initializes_correctly()
    {
        $serviceType = new ServiceTypeDTO(10, 'Packing', 'Упаковка', 1, 'standard');

        $dto = new ServiceDTO(
            id: 501,
            date_created: '2024-01-01T10:00:00Z',
            date_performed: null,
            service_type_id: 10,
            status_id: 200,
            status: 'Completed',
            comment: 'Fragile items',
            serviceType: $serviceType
        );

        $this->assertEquals('Completed', $dto->status);
        $this->assertInstanceOf(ServiceTypeDTO::class, $dto->serviceType);
    }

    public function test_service_from_array()
    {
        $data = [
            'id' => 502,
            'date_created' => '2024-02-01',
            'date_performed' => null,
            'service_type_id' => 11,
            'status_id' => 100,
            'status' => 'Pending',
            'comment' => 'Extra box',
            'serviceType' => [
                'id' => 11,
                'name_en' => 'Boxing',
                'name_ru' => 'Коробка',
                'type_id' => 2,
                'type' => 'premium'
            ]
        ];

        $dto = ServiceDTO::fromArray($data);
        $this->assertEquals('Boxing', $dto->serviceType->name_en);
        $this->assertEquals('Pending', $dto->status);
    }
}
