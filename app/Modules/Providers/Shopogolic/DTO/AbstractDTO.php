<?php

namespace App\Modules\Providers\Shopogolic\DTO;

abstract class AbstractDTO
{
    /**
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
