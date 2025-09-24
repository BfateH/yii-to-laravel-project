<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ItemDTO;
use Tests\TestCase;

class ItemDTOTest extends TestCase
{
    public function test_item_dto_initializes_correctly()
    {
        $dto = new ItemDTO(
            id: 1001,
            description: 'Wireless headphones',
            qty: 2,
            price: 99.99,
            delivery: 5.0,
            sku: 'WH-2024',
            url: 'https://example.com/headphones',
            size: 'M',
            color: 'Black',
            comment: 'Noise cancelling'
        );

        $this->assertEquals('Wireless headphones', $dto->description);
        $this->assertEquals(2, $dto->qty);
        $this->assertEquals(99.99, $dto->price);
    }

    public function test_item_from_array()
    {
        $data = [
            'id' => '1002',
            'description' => 'T-shirt',
            'qty' => '3',
            'price' => '19.99',
            'delivery' => null,
            'sku' => 'TS-001',
            'url' => 'https://example.com/tshirt',
            'size' => 'L',
            'color' => 'Red',
            'comment' => null
        ];

        $dto = ItemDTO::fromArray($data);
        $this->assertEquals(1002, $dto->id);
        $this->assertEquals(3, $dto->qty);
        $this->assertNull($dto->delivery);
        $this->assertNull($dto->comment);
    }

    public function test_item_to_array()
    {
        $dto = new ItemDTO(1, 'Test', 1, 10.0, null, 'SKU', 'http://test', null, null, null);
        $array = $dto->toArray();
        $this->assertEquals(10.0, $array['price']);
        $this->assertNull($array['delivery']);
    }
}
