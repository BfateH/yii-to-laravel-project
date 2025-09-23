<?php

namespace App\Modules\Alerts\Interfaces;

use App\Models\Alert;

interface ChannelInterface
{
    /**
     * @param Alert $alert
     * @return bool
     */
    public function send(Alert $alert): bool;
}
