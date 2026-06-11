<?php

namespace Tests\Support;

use App\Models\User;

trait ActsAsAdmin
{
    protected function actingAsAdmin(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }
}
