<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000000,
            'currency' => 'IRR',
        ]);
        $this->category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
        ]);
    }

    public function test_can_list_transactions(): void
    {
        Sanctum::actingAs($this->user);

        Transaction::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/transactions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_income_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000000,
            'currency' => 'IRR',
        ]);

        $data = [
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 500000,
            'currency' => 'IRR',
            'transaction_date' => now()->toDateString(),
            'description' => 'دریافت حقوق',
        ];

        $response = $this->postJson('/api/transactions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.amount', 500000);

        // Verify balance was incremented
        $account->refresh();
        $this->assertEquals(1500000, $account->current_balance);
    }

    public function test_can_create_expense_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000000,
            'currency' => 'IRR',
        ]);

        $data = [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 200000,
            'currency' => 'IRR',
            'transaction_date' => now()->toDateString(),
            'category_id' => $this->category->id,
            'description' => 'خرید مواد غذایی',
        ];

        $response = $this->postJson('/api/transactions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount', 200000);

        // Verify balance was decremented
        $account->refresh();
        $this->assertEquals(800000, $account->current_balance);
    }

    public function test_can_create_transfer(): void
    {
        Sanctum::actingAs($this->user);

        $toAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 0,
            'currency' => 'IRR',
        ]);

        $data = [
            'from_account_id' => $this->account->id,
            'to_account_id' => $toAccount->id,
            'amount' => 300000,
            'currency' => 'IRR',
            'transaction_date' => now()->toDateString(),
            'description' => 'انتقال وجه',
        ];

        $response = $this->postJson('/api/transactions/transfer', $data);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.amount', 300000);

        // Verify balances
        $this->account->refresh();
        $toAccount->refresh();
        $this->assertEquals(700000, $this->account->current_balance);
        $this->assertEquals(300000, $toAccount->current_balance);
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        Sanctum::actingAs($this->user);

        $source = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 100000,
            'type' => 'bank',
            'currency' => 'IRR',
        ]);
        $toAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'IRR',
        ]);

        $data = [
            'from_account_id' => $source->id,
            'to_account_id' => $toAccount->id,
            'amount' => 2000000,
            'currency' => 'IRR',
            'transaction_date' => now()->toDateString(),
        ];

        $response = $this->postJson('/api/transactions/transfer', $data);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Insufficient balance in source account');
    }

    public function test_can_filter_transactions_by_type(): void
    {
        Sanctum::actingAs($this->user);

        Transaction::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);
        Transaction::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        Transaction::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);

        $response = $this->getJson('/api/transactions?type=expense');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_transactions_by_date_range(): void
    {
        Sanctum::actingAs($this->user);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2024-01-15',
        ]);
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2024-02-15',
        ]);
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_date' => '2024-03-15',
        ]);

        $response = $this->getJson('/api/transactions?from_date=2024-01-01&to_date=2024-02-28');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_delete_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 1000000,
            'currency' => 'IRR',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100000,
        ]);

        $account->decrement('current_balance', 100000);
        $account->refresh();

        $response = $this->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertNoContent();

        // Verify balance was restored to starting value
        $account->refresh();
        $this->assertEquals(1000000, $account->current_balance);
    }

    public function test_cannot_access_other_users_transactions(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/transactions/{$transaction->id}");

        $response->assertNotFound();
    }
}
