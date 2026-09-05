<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $bank;
    private Account $wallet;
    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionService();
        $this->user = User::factory()->create([
            'name' => 'Hamid',
            'email' => 'hamid@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->bank = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Melli Bank',
            'type' => 'bank',
            'currency' => 'IRR',
            'initial_balance' => 10000000, // 1,000,000 toman
            'current_balance' => 10000000,
        ]);
        $this->wallet = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Cash Wallet',
            'type' => 'cash',
            'currency' => 'IRR',
            'initial_balance' => 500000,
            'current_balance' => 500000,
        ]);
    }

    public function test_record_income_increases_account_balance()
    {
        $startBalance = $this->bank->current_balance;
        $income = 2000000;

        $tx = $this->service->recordIncome(
            $this->user,
            $this->bank,
            $income,
            'Salary deposit',
        );

        $this->bank->refresh();

        $this->assertSame($income, $tx->amount);
        $this->assertSame('income', $tx->type);
        $this->assertSame($startBalance + $income, $this->bank->current_balance);
    }

    public function test_record_expense_decreases_account_balance()
    {
        $startBalance = $this->bank->current_balance;
        $expense = 1500000;

        $tx = $this->service->recordExpense(
            $this->user,
            $this->bank,
            $expense,
            'Grocery shopping',
        );

        $this->bank->refresh();

        $this->assertSame($expense, $tx->amount);
        $this->assertSame('expense', $tx->type);
        $this->assertSame($startBalance - $expense, $this->bank->current_balance);
    }

    public function test_expense_throws_for_insufficient_balance()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->recordExpense(
            $this->user,
            $this->wallet,
            10000000, // wallet has 500,000
            'Too expensive',
        );
    }

    public function test_negative_or_zero_amount_throws()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordIncome($this->user, $this->bank, 0, 'zero');
    }

    public function test_transfer_moves_funds_between_accounts()
    {
        $bankStart = $this->bank->current_balance;
        $walletStart = $this->wallet->current_balance;
        $amount = 1000000;

        $result = $this->service->transfer(
            $this->user,
            $this->bank,
            $this->wallet,
            $amount,
            'Move to cash',
        );

        $this->bank->refresh();
        $this->wallet->refresh();

        $this->assertSame($bankStart - $amount, $this->bank->current_balance);
        $this->assertSame($walletStart + $amount, $this->wallet->current_balance);
        $this->assertArrayHasKey('outgoing', $result);
        $this->assertArrayHasKey('incoming', $result);
        $this->assertNotEmpty($result['transfer_group']);
    }

    public function test_transfer_to_same_account_throws()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transfer(
            $this->user,
            $this->bank,
            $this->bank,
            100,
            'oops',
        );
    }

    public function test_recalculate_balance_recomputes_from_transactions()
    {
        $this->service->recordIncome($this->user, $this->bank, 5000000, 'salary');
        $this->service->recordExpense($this->user, $this->bank, 2000000, 'rent');

        $balance = $this->service->recalculateBalance($this->bank->fresh());

        $this->bank->refresh();
        $expected = $this->bank->initial_balance + 5000000 - 2000000;
        $this->assertSame($expected, $balance);
        $this->assertSame($expected, $this->bank->current_balance);
    }

    public function test_reverse_transaction_creates_opposite()
    {
        $tx = $this->service->recordExpense($this->user, $this->bank, 1500000, 'Test expense');
        $balanceAfterExpense = $this->bank->fresh()->current_balance;

        $reversal = $this->service->reverseTransaction($tx, 'mistake');

        $this->bank->refresh();
        $this->assertSame('income', $reversal->type);
        $this->assertSame($balanceAfterExpense + 1500000, $this->bank->current_balance);
    }

    public function test_get_monthly_statistics()
    {
        $month = now()->format('Y-m');
        $this->service->recordIncome($this->user, $this->bank, 10000000, 'salary');
        $this->service->recordExpense($this->user, $this->bank, 3000000, 'rent');

        $stats = $this->service->getMonthlyStatistics($this->user, $month, 'IRR');

        $this->assertSame(10000000, $stats['income']);
        $this->assertSame(3000000, $stats['expense']);
        $this->assertSame(7000000, $stats['net']);
    }
}
