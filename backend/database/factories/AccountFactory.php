<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $types = ['bank', 'cash', 'credit_card', 'wallet', 'investment'];
        $currencies = ['IRR', 'USD', 'EUR'];
        
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement([
                'بانک ملی', 'بانک صادرات', 'کیف پول', 'پس‌انداز نقدی',
            ]),
            'type' => $this->faker->randomElement($types),
            'currency' => $this->faker->randomElement($currencies),
            'initial_balance' => $this->faker->numberBetween(0, 10000000),
            'current_balance' => $this->faker->numberBetween(0, 10000000),
            'bank_name' => $this->faker->optional()->randomElement([
                'بانک ملی ایران', 'بانک صادرات', 'بانک پاسارگاد', 'بانک ملت',
            ]),
            'is_active' => true,
            'is_archived' => false,
        ];
    }
}
