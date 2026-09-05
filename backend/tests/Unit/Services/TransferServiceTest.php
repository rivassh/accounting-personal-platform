<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransferService $service;
    protected User $user;
    protected Account $fromAccount;
    protected Account $toAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransferService();
        $this->user = User::factory()->create();
        $this->fromAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000000,
            'currency' => 'IRR',
        ]);
        $this->toAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 500000,
            'currency' => 'IRR',
        ]);
    }

    public function test_successful_transfer(): void
    {
        $transaction = $this->service->transfer(
            userId: $this->user->id,
            fromAccountId: $this->fromAccount->id,
            toAccountId: $this->toAccount->id,
            amount: 300000,
            currency: 'IRR',
            transactionDate: now()->toDateString(),
            description: 'Test transfer'
        );

        $this->assertEquals('transfer', $transaction->type);
        $this->assertEquals(300000, $transaction->amount);

        $this->fromAccount->refresh();
        $this->toAccount->refresh();
        $this->assertEquals(700000, $this->fromAccount->current_balance);
        $this->assertEquals(800000, $this->toAccount->current_balance);
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->service->transfer(
            userId: $this->user->id,
            fromAccountId: $this->fromAccount->id,
            toAccountId: $this->toAccount->id,
            amount: 2000000,
            currency: 'IRR',
            transactionDate: now()->toDateString()
        );
    }

    public function test_transfer_fails_with_same_account(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same account');

        $this->service->transfer(
            userId: $this->user->id,
            fromAccountId: $this->fromAccount->id,
            toAccountId: $this->fromAccount->id,
            amount: 100000,
            currency: 'IRR',
            transactionDate: now()->toDateString()
        );
    }

    public function test_transfer_fails_with_currency_mismatch(): void
    {
        $this->toAccount->update(['currency' => 'USD']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->transfer(
            userId: $this->user->id,
            fromAccountId: $this->fromAccount->id,
            toAccountId: $this->toAccount->id,
            amount: 100000,
            currency: 'IRR',
            transactionDate: now()->toDateString()
        );
    }

    public function test_transfer_fails_with_inactive_account(): void
    {
        $this->fromAccount->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not active');

        $this->service->transfer(
            userId: $this->user->id,
            fromAccountId: $this->fromAccount->id,
            toAccountId: $this->toAccount->id,
            amount: 100000,
            currency: 'IRR',
            transactionDate: now()->toDateString()
        );
    }
}
