<?php

namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use Tests\TestCase;

class CourierDTOTest extends TestCase
{
    public function test_courier_dto_initializes_correctly()
    {
        $dto = new CourierDTO(101, 'DHL Express', 1, 25.50);

        $this->assertEquals(101, $dto->id);
        $this->assertEquals('DHL Express', $dto->name);
        $this->assertEquals(1, $dto->warehouse_id);
        $this->assertEquals(25.50, $dto->calculated_price);
    }

    public function test_courier_dto_properties_are_typed()
    {
        $dto = new CourierDTO(101, 'FedEx', 2, 18.75);

        $this->assertIsInt($dto->id);
        $this->assertIsString($dto->name);
        $this->assertIsInt($dto->warehouse_id);
        $this->assertIsFloat($dto->calculated_price);
    }
}
