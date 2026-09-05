<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['income', 'expense']);
        
        $incomeNames = ['حقوق', 'درآمد فریلنسری', 'سرمایه‌گذاری', 'هدیه'];
        $expenseNames = ['خوراک', 'حمل و نقل', 'خرید', 'قبوض', 'سلامت'];
        
        return [
            'user_id' => User::factory(),
            'name' => $type === 'income' 
                ? $this->faker->randomElement($incomeNames)
                : $this->faker->randomElement($expenseNames),
            'type' => $type,
            'parent_id' => null,
            'level' => 0,
            'icon' => $this->faker->randomElement(['briefcase', 'car', 'utensils', 'shopping-bag']),
            'color' => $type === 'income' ? '#10b981' : '#ef4444',
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
