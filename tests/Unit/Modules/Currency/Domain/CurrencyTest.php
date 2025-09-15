<?php

namespace Tests\Unit\Modules\Currency\Domain;

use App\Modules\Currency\Domain\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_it_can_be_created_and_properties_are_accessible()
    {
        $code = 'USD';
        $name = 'US Dollar';

        $currency = new Currency($code, $name);

        $this->assertEquals($code, $currency->getCode());
        $this->assertEquals($name, $currency->getName());
    }
}
