<?php

namespace App\Services;

use Illuminate\Support\Str;

class SlugCreatorService
{
    public function createSlug($title)
    {
        $slug = Str::slug($title . '-' . Str::random(3));
        return $slug;
    }
}
