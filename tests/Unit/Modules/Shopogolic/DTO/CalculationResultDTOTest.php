<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\CalculationResultDTO;
use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use Tests\TestCase;

class CalculationResultDTOTest extends TestCase
{
    public function test_calculation_result_dto_initializes_correctly()
    {
        $couriers = [
            new CourierDTO(1, 'DHL', 1, 25.5),
            new CourierDTO(2, 'FedEx', 1, 30.0)
        ];

        $dto = new CalculationResultDTO(2.5, 30.0, 20.0, 15.0, $couriers);

        $this->assertEquals(2.5, $dto->weight);
        $this->assertEquals(30.0, $dto->length);
        $this->assertCount(2, $dto->couriers);
        $this->assertInstanceOf(CourierDTO::class, $dto->couriers[0]);
    }

    public function test_calculation_result_to_array()
    {
        $dto = new CalculationResultDTO(1.0, 10.0, 10.0, 10.0, []);

        $array = $dto->toArray();
        $this->assertIsFloat($array['weight']);
        $this->assertIsArray($array['couriers']);
        $this->assertEquals(1.0, $array['weight']);
    }
}
