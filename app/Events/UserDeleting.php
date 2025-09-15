<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user)
    {
        // пока никакой логики, просто на будущее
    }
}
