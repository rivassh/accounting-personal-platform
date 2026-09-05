<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\BalanceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BalanceCalculationService $service;
    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BalanceCalculationService();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
    }

    public function test_calculate_balance_with_no_transactions(): void
    {
        $balance = $this->service->calculateBalance($this->account->id);
        $this->assertEquals(1000000, $balance);
    }

    public function test_calculate_balance_with_income_and_expense(): void
    {
        $this->account->transactions()->createMany([
            [
                'user_id' => $this->user->id,
                'type' => 'income',
                'amount' => 500000,
                'currency' => 'IRR',
                'transaction_date' => now(),
            ],
            [
                'user_id' => $this->user->id,
                'type' => 'expense',
                'amount' => 300000,
                'currency' => 'IRR',
                'transaction_date' => now(),
            ],
        ]);

        $balance = $this->service->calculateBalance($this->account->id);
        $this->assertEquals(1200000, $balance);
    }

    public function test_get_transaction_sum(): void
    {
        $this->account->transactions()->createMany([
            ['user_id' => $this->user->id, 'type' => 'income', 'amount' => 500000, 'currency' => 'IRR', 'transaction_date' => now()],
            ['user_id' => $this->user->id, 'type' => 'income', 'amount' => 200000, 'currency' => 'IRR', 'transaction_date' => now()],
            ['user_id' => $this->user->id, 'type' => 'expense', 'amount' => 100000, 'currency' => 'IRR', 'transaction_date' => now()],
        ]);

        $sum = $this->service->getTransactionSum($this->account->id);
        $this->assertEquals(600000, $sum);
    }

    public function test_verify_balance_when_balanced(): void
    {
        $result = $this->service->verifyBalance($this->account->id);
        
        $this->assertTrue($result['is_balanced']);
        $this->assertEquals(0, $result['difference']);
    }

    public function test_verify_balance_when_mismatched(): void
    {
        $this->account->update(['current_balance' => 999999]);
        
        $result = $this->service->verifyBalance($this->account->id);
        
        $this->assertFalse($result['is_balanced']);
        $this->assertEquals(-1, $result['difference']);
    }

    public function test_get_account_summary(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 500000,
            'currency' => 'IRR',
            'type' => 'cash',
        ]);
        Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 200000,
            'currency' => 'USD',
            'type' => 'bank',
        ]);

        $summary = $this->service->getAccountSummary($this->user->id);

        $this->assertEquals(1700000, $summary['total_balance']);
        $this->assertArrayHasKey('IRR', $summary['by_currency']);
        $this->assertArrayHasKey('USD', $summary['by_currency']);
        $this->assertArrayHasKey('bank', $summary['by_type']);
        $this->assertArrayHasKey('cash', $summary['by_type']);
    }
}
