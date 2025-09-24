<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\CountryDTO;
use Tests\TestCase;

class CountryDTOTest extends TestCase
{
    public function test_country_dto_initializes_correctly()
    {
        $dto = new CountryDTO(1, 'Россия', 'Russia', 'RU');
        $this->assertEquals('RU', $dto->code);
        $this->assertEquals('Russia', $dto->name_en);
    }

    public function test_country_to_array()
    {
        $dto = new CountryDTO(2, 'Deutschland', 'Germany', 'DE');
        $array = $dto->toArray();
        $this->assertEquals('DE', $array['code']);
        $this->assertEquals('Germany', $array['name_en']);
    }
}
