<?php

namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\WarehouseDTO;
use Tests\TestCase;

class WarehouseDTOTest extends TestCase
{
    public function test_warehouse_dto_initializes_correctly()
    {
        $dto = new WarehouseDTO(1, 'Warehouse #1 (US)', 'US', 'USD');

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('Warehouse #1 (US)', $dto->name);
        $this->assertEquals('US', $dto->country_code);
        $this->assertEquals('USD', $dto->currency);
    }

    public function test_warehouse_dto_properties_are_typed()
    {
        $dto = new WarehouseDTO(2, 'Test', 'CN', 'CNY');

        $this->assertIsInt($dto->id);
        $this->assertIsString($dto->name);
        $this->assertIsString($dto->country_code);
        $this->assertIsString($dto->currency);
    }
}
