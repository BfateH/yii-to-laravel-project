<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\HsCodeDTO;
use Tests\TestCase;

class HsCodeDTOTest extends TestCase
{
    public function test_hscode_dto_initializes_correctly()
    {
        $dto = new HsCodeDTO(501, '8517.62', 'Телефоны', 'Phones');
        $this->assertEquals('8517.62', $dto->code);
        $this->assertEquals('Phones', $dto->name_en);
    }

    public function test_hscode_to_array()
    {
        $dto = new HsCodeDTO(1, '1234.56', 'Тест', 'Test');
        $array = $dto->toArray();
        $this->assertEquals('1234.56', $array['code']);
    }
}
