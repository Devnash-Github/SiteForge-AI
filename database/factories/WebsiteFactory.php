<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Website;
use App\Services\Websites\ThemePresets;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Website> */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => $this->faker->company(),
            'slug' => Str::slug($this->faker->unique()->words(3, true)),
            'theme' => ThemePresets::default(),
            'status' => Website::STATUS_DRAFT,
            'generation_status' => Website::GEN_IDLE,
        ];
    }
}
