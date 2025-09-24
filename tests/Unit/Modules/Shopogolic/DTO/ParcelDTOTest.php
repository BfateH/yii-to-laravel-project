<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ParcelDTO;
use App\Modules\Providers\Shopogolic\DTO\ParcelItemDTO;
use Tests\TestCase;

class ParcelDTOTest extends TestCase
{
    public function test_parcel_dto_initializes_correctly()
    {
        $items = [new ParcelItemDTO(1, 'Phone', 'Телефон', 'Phone', 1, 500.0, 'SKU', 'url', 'Electronics', 'Apple', 'Black/128GB', 8517)];

        $dto = new ParcelDTO(
            id: 7001,
            track: 'TR123456789',
            warehouse_id: 1,
            courier_id: 2,
            user_id: 100,
            address_id: 200,
            status_id: 500,
            status: 'Sent',
            weight: 0.5,
            length: 20.0,
            width: 15.0,
            height: 5.0,
            insurance: 1,
            items: $items
        );

        $this->assertEquals('TR123456789', $dto->track);
        $this->assertEquals(0.5, $dto->weight);
        $this->assertCount(1, $dto->items);
    }

    public function test_parcel_to_array()
    {
        $dto = new ParcelDTO(1, null, 1, 1, 1, 1, 50, 'New', 1.0, 10.0, 10.0, 10.0, 0);
        $array = $dto->toArray();
        $this->assertEquals(1.0, $array['weight']);
        $this->assertNull($array['track']);
    }
}
