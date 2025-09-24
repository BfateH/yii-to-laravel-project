<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\RegionDTO;
use Tests\TestCase;

class RegionDTOTest extends TestCase
{
    public function test_region_dto_initializes_correctly()
    {
        $dto = new RegionDTO(
            id: 30,
            name: 'California',
            prefix: 'State of',
            suffix: 'USA',
            country_id: 1,
            country: ['code' => 'US']
        );

        $this->assertEquals('California', $dto->name);
        $this->assertEquals('State of', $dto->prefix);
    }

    public function test_region_to_array()
    {
        $dto = new RegionDTO(1, 'Bavaria', null, null, 49, null);
        $array = $dto->toArray();
        $this->assertEquals('Bavaria', $array['name']);
        $this->assertNull($array['suffix']);
    }
}
