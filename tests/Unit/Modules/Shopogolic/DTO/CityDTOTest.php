<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\CityDTO;
use Tests\TestCase;

class CityDTOTest extends TestCase
{
    public function test_city_dto_initializes_correctly()
    {
        $dto = new CityDTO(
            id: 101,
            name: 'Berlin',
            prefix: 'Stadt',
            country_id: 49,
            region_id: 5,
            country: ['code' => 'DE'],
            region: ['name' => 'Berlin']
        );

        $this->assertEquals('Berlin', $dto->name);
        $this->assertEquals(49, $dto->country_id);
        $this->assertEquals('Stadt', $dto->prefix);
    }

    public function test_city_to_array()
    {
        $dto = new CityDTO(1, 'Paris', null, 33, null, null, null);
        $array = $dto->toArray();
        $this->assertEquals('Paris', $array['name']);
        $this->assertNull($array['prefix']);
    }
}
