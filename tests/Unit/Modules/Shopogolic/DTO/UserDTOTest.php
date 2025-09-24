<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\UserDTO;
use Tests\TestCase;

class UserDTOTest extends TestCase
{
    public function test_user_dto_initializes_correctly()
    {
        $dto = new UserDTO(
            id: 1001,
            email: 'user@example.com',
            external_id: 'ext-123',
            name: 'John Doe',
            firstname: 'John',
            lastname: 'Doe',
            midname: 'Michael',
            phone: '+1234567890',
            language: 'en',
            country_code: 'US'
        );

        $this->assertEquals('user@example.com', $dto->email);
        $this->assertEquals('John Michael Doe', $dto->getFullName());
    }

    public function test_user_to_array()
    {
        $dto = new UserDTO(1, 'test@test.com', null, 'Test', null, null, null, null, null, 'RU');
        $array = $dto->toArray();
        $this->assertEquals('RU', $array['country_code']);
        $this->assertNull($array['firstname']);
    }

    public function test_get_full_name()
    {
        $dto = new UserDTO(1, '', null, '', 'Иван', 'Иванов', 'Иванович', null, null, null);
        $this->assertEquals('Иван Иванович Иванов', $dto->getFullName());

        $dto2 = new UserDTO(2, '', null, '', null, 'Smith', null, null, null, null);
        $this->assertEquals('Smith', $dto2->getFullName());
    }
}
