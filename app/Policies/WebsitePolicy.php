<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        return $website->business()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Website $website): bool
    {
        return $this->view($user, $website);
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->view($user, $website);
    }
}
