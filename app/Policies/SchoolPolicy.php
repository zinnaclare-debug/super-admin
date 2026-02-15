<?php

namespace App\Policies;

use App\Models\User;
use App\Models\School;

class SchoolPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }
}
