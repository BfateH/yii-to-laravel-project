<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ParcelItemDTO;
use Tests\TestCase;

class ParcelItemDTOTest extends TestCase
{
    public function test_parcel_item_dto_initializes_correctly()
    {
        $dto = new ParcelItemDTO(
            id: 2001,
            description: 'Smartphone',
            descr_ru: 'Смартфон',
            descr_en: 'Smartphone',
            qty: 1,
            cost: 699.99,
            sku: 'SM-2024',
            url: 'https://example.com/phone',
            type: 'MF',
            brand: 'Samsung',
            color_size: 'Black/256GB',
            hscode_id: 8517
        );

        $this->assertEquals('Смартфон', $dto->descr_ru);
        $this->assertEquals(8517, $dto->hscode_id);
    }

    public function test_parcel_item_from_array()
    {
        $data = [
            'id' => '2002',
            'description' => 'Laptop',
            'descr_ru' => 'Ноутбук',
            'descr_en' => 'Laptop',
            'qty' => '1',
            'cost' => '1200.00',
            'sku' => 'LT-001',
            'url' => 'https://example.com/laptop',
            'type' => 'OR',
            'brand' => 'Dell',
            'color_size' => 'Silver/16GB',
            'hscode_id' => '8471'
        ];

        $dto = ParcelItemDTO::fromArray($data);
        $this->assertEquals('Ноутбук', $dto->descr_ru);
        $this->assertEquals(8471, $dto->hscode_id);
        $this->assertIsInt($dto->hscode_id);
    }
}
