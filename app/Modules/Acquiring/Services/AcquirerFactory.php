<?php

namespace App\Modules\Acquiring\Services;

use App\Modules\Acquiring\Contracts\AcquirerInterface;
use App\Modules\Acquiring\Enums\AcquirerType;
use Illuminate\Support\Manager;

class AcquirerFactory extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'tinkoff'; // Заглушка
    }

    public function createTinkoffDriver(): AcquirerInterface
    {
        return new TinkoffAcquirer();
    }

    public function make(AcquirerType $type): AcquirerInterface
    {
        return $this->driver($type->value);
    }
}
