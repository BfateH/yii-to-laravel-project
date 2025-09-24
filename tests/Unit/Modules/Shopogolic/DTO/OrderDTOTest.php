<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\ItemDTO;
use App\Modules\Providers\Shopogolic\DTO\OrderDTO;
use Tests\TestCase;

class OrderDTOTest extends TestCase
{
    public function test_order_dto_initializes_correctly()
    {
        $items = [new ItemDTO(1, 'Item', 1, 10.0, null, 'SKU', 'url', null, null, null)];
        $services = null;

        $dto = new OrderDTO(
            id: 5001,
            name: 'Order #5001',
            warehouse_id: 1,
            user_id: 100,
            status_id: 300,
            status: 'Paid',
            date_created: '2024-01-01T10:00:00Z',
            items: $items,
            services: $services
        );

        $this->assertEquals('Paid', $dto->status);
        $this->assertEquals(5001, $dto->id);
        $this->assertCount(1, $dto->items);
    }

    public function test_order_to_array()
    {
        $dto = new OrderDTO(1, null, 1, 1, 100, 'Draft');
        $array = $dto->toArray();
        $this->assertEquals('Draft', $array['status']);
        $this->assertNull($array['name']);
    }
}
