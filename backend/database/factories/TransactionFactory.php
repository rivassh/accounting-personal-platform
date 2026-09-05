<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['income', 'expense']);
        
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => null,
            'type' => $type,
            'amount' => $this->faker->numberBetween(10000, 5000000),
            'currency' => 'IRR',
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'description' => $type === 'income' 
                ? $this->faker->randomElement(['دریافت حقوق', 'پاداش', 'هدیه', 'بازگشت وجه'])
                : $this->faker->randomElement(['خرید', 'پرداخت قبض', 'هزینه غذا', 'حمل و نقل']),
        ];
    }
}
