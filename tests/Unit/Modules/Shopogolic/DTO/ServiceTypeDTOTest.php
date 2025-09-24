<?php

namespace Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ServiceTypeDTO;
use Tests\TestCase;

class ServiceTypeDTOTest extends TestCase
{
    public function test_service_type_dto_initializes_correctly()
    {
        $dto = new ServiceTypeDTO(1, 'Express Delivery', 'Экспресс доставка', 3, 'express');
        $this->assertEquals('express', $dto->type);
        $this->assertEquals('Экспресс доставка', $dto->name_ru);
    }

    public function test_service_type_from_array()
    {
        $data = ['id' => 2, 'name_en' => 'Insurance', 'name_ru' => 'Страховка', 'type_id' => 4, 'type' => 'optional'];
        $dto = ServiceTypeDTO::fromArray($data);
        $this->assertEquals('Insurance', $dto->name_en);
        $this->assertEquals(4, $dto->type_id);
    }
}
