<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgencyStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Auto Sahara', 'Nouakchott Motors', 'Mauritanie Auto', 'Saharian Cars',
            'Atlas Auto', 'Tevragh Auto', 'Premium MR Cars', 'Boursa Garage',
            'Sahel Motors', 'Capital Auto MR', 'Desert Wheels', 'AlAmin Motors',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::AGENCY,
            'name' => $name.' Manager',
        ]);

        return [
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'logo_url' => null,
            'description' => $this->faker->sentence(15),
            'address' => $this->faker->streetAddress().', Nouakchott',
            'city_id' => City::where('region', 'Nouakchott')->inRandomOrder()->first()?->id,
            'rc_number' => 'RC-'.$this->faker->numerify('######'),
            'phone_whatsapp' => '222'.$this->faker->numerify('########'),
            'phone_call' => '222'.$this->faker->numerify('########'),
            'email' => $this->faker->companyEmail(),
            'status' => AgencyStatus::VERIFIED,
            'verified_at' => now()->subMonths($this->faker->numberBetween(1, 18)),
            'subscription_tier' => $this->faker->randomElement(['free', 'pro', 'premium']),
            'quota_active_listings' => 50,
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Agency $agency): void {
            $lat = 18.0735 + $this->faker->randomFloat(4, -0.05, 0.05);
            $lng = -15.9785 + $this->faker->randomFloat(4, -0.05, 0.05);

            DB::statement(
                'UPDATE agencies SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [$lng, $lat, $agency->id]
            );

            $agency->user->syncRoles([UserRole::AGENCY->value]);
        });
    }
}
