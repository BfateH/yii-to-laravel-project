<?php
namespace Tests\Unit\Modules\Shopogolic\DTO;

use App\Modules\Providers\Shopogolic\DTO\AddressDTO;
use Tests\TestCase;

class AddressDTOTest extends TestCase
{
    public function test_address_dto_initializes_correctly()
    {
        $dto = new AddressDTO(
            id: 123,
            user_id: 456,
            country_code: 'RU',
            zipcode: '123456',
            address_line1: 'ул. Ленина',
            address_line2: 'д. 10',
            region: 'Московская обл.',
            city: 'Москва',
            street: 'Ленина',
            house: '10',
            apt: '5',
            phone: '+79991234567',
            firstname: 'Иван',
            lastname: 'Иванов',
            midname: 'Иванович',
            passport_series: '1234',
            passport_number: '567890',
            passport_agency: 'УФМС',
            passport_date: '01.01.2020',
            inn: '123456789012',
            birth: '01.01.1990',
            email: 'ivan@example.com',
            company: 'ООО Ромашка',
            company_number: '123456789',
            vat_number: 'RU123456789',
            user: ['id' => 456],
            country: ['code' => 'RU'],
            relatedRegion: ['id' => 1],
            relatedCity: ['id' => 2]
        );

        $this->assertEquals(123, $dto->id);
        $this->assertEquals('Москва', $dto->city);
        $this->assertEquals('+79991234567', $dto->phone);
        $this->assertEquals('ООО Ромашка', $dto->company);
    }

    public function test_address_dto_to_array()
    {
        $dto = new AddressDTO(
            id: 1,
            user_id: null,
            country_code: 'US',
            zipcode: '10001',
            address_line1: null,
            address_line2: null,
            region: null,
            city: 'New York',
            street: '5th Ave',
            house: '123',
            apt: null,
            phone: '+1234567890',
            firstname: null,
            lastname: null,
            midname: null,
            passport_series: null,
            passport_number: null,
            passport_agency: null,
            passport_date: null,
            inn: null,
            birth: null,
            email: null,
            company: null,
            company_number: null,
            vat_number: null,
            user: null,
            country: null,
            relatedRegion: null,
            relatedCity: null
        );

        $array = $dto->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertNull($array['user_id']);
        $this->assertEquals('New York', $array['city']);
    }

    public function test_get_full_address()
    {
        $dto = new AddressDTO(
            id: 1,
            user_id: null,
            country_code: 'RU',
            zipcode: '123456',
            address_line1: 'ул. Тверская',
            address_line2: 'офис 10',
            region: 'Москва',
            city: 'Москва',
            street: 'Тверская',
            house: '15',
            apt: '20',
            phone: '+79991234567'
        );

        $expected = 'ул. Тверская, офис 10, Тверская, 15, кв. 20, Москва, Москва, 123456';
        $this->assertEquals($expected, $dto->getFullAddress());
    }
}
