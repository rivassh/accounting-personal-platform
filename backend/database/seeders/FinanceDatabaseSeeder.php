<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FinanceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'کاربر آزمایشی',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->seedAccounts($user);
        $this->seedCategories($user);
    }

    private function seedAccounts(User $user): void
    {
        $accounts = [
            [
                'name' => 'حساب بانک ملی',
                'type' => 'bank',
                'currency' => 'IRR',
                'initial_balance' => 10000000,
                'current_balance' => 10000000,
                'bank_name' => 'بانک ملی ایران',
                'account_number' => '1234567890',
                'color' => '#1d4ed8',
                'icon' => 'bank',
            ],
            [
                'name' => 'کیف پول نقدی',
                'type' => 'cash',
                'currency' => 'IRR',
                'initial_balance' => 2000000,
                'current_balance' => 2000000,
                'color' => '#16a34a',
                'icon' => 'wallet',
            ],
            [
                'name' => 'کارت اعتباری',
                'type' => 'credit_card',
                'currency' => 'IRR',
                'initial_balance' => 0,
                'current_balance' => 0,
                'bank_name' => 'بانک سامان',
                'card_number' => '6037-9977-1234-5678',
                'color' => '#dc2626',
                'icon' => 'credit-card',
            ],
            [
                'name' => 'حساب دلاری',
                'type' => 'bank',
                'currency' => 'USD',
                'initial_balance' => 0,
                'current_balance' => 0,
                'bank_name' => 'بانک پارسیان',
                'account_number' => '9876543210',
                'color' => '#7c3aed',
                'icon' => 'investment',
            ],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['user_id' => $user->id, 'name' => $account['name']],
                $account
            );
        }
    }

    private function seedCategories(User $user): void
    {
        $expenseCategories = [
            'خوراک و آشامیدنی' => ['رستوران', 'خرید مواد غذایی', 'کافه'],
            'مسکن' => ['اجاره', 'قبوض', 'تعمیرات'],
            'حمل و نقل' => ['سوخت', 'تاکسی', 'مترو و اتوبوس', 'تعمیر خودرو'],
            'سلامت' => ['دارو', 'دکتر', 'بیمه درمانی'],
            'خرید' => ['پوشاک', 'لوازم خانگی', 'الکترونیک'],
            'تحصیل' => ['کتاب', 'دوره آموزشی', 'دانشگاه'],
            'تفریح' => ['سینما', 'سفر', 'بازی'],
            'مواد غذایی' => ['نان', 'میوه و سبزی', 'گوشت'],
            'ارتباطات' => ['اینترنت', 'موبایل'],
            'سایر' => ['هدیه', 'کمک', 'misc'],
        ];

        $incomeCategories = [
            'درآمد' => ['حقوق', 'پاداش', 'اضافه‌کاری'],
            'کسب و کار' => ['فریلنسری', 'فروش', 'سود سهام'],
            'سرمایه‌گذاری' => ['سود بانکی', 'اجاره'],
            'سایر درآمد' => ['هدیه', 'بازپرداخت', 'misc'],
        ];

        $colors = [
            'خوراک و آشامیدنی' => '#ef4444',
            'مسکن' => '#3b82f6',
            'حمل و نقل' => '#f97316',
            'سلامت' => '#22c55e',
            'خرید' => '#8b5cf6',
            'تحصیل' => '#06b6d4',
            'تفریح' => '#ec4899',
            'مواد غذایی' => '#eab308',
            'ارتباطات' => '#6366f1',
            'سایر' => '#6b7280',
            'درآمد' => '#10b981',
            'کسب و کار' => '#14b8a6',
            'سرمایه‌گذاری' => '#0ea5e9',
            'سایر درآمد' => '#8b5cf6',
        ];

        $sortOrder = 0;
        foreach ($expenseCategories as $parent => $children) {
            $parentCategory = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $parent, 'type' => 'expense', 'parent_id' => null],
                [
                    'level' => 0,
                    'color' => $colors[$parent] ?? '#6b7280',
                    'icon' => 'folder',
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            foreach ($children as $child) {
                Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $child, 'type' => 'expense', 'parent_id' => $parentCategory->id],
                    [
                        'level' => 1,
                        'color' => $colors[$parent] ?? '#6b7280',
                        'icon' => 'tag',
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }

        foreach ($incomeCategories as $parent => $children) {
            $parentCategory = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $parent, 'type' => 'income', 'parent_id' => null],
                [
                    'level' => 0,
                    'color' => $colors[$parent] ?? '#10b981',
                    'icon' => 'folder',
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            foreach ($children as $child) {
                Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $child, 'type' => 'income', 'parent_id' => $parentCategory->id],
                    [
                        'level' => 1,
                        'color' => $colors[$parent] ?? '#10b981',
                        'icon' => 'tag',
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }
    }
}
