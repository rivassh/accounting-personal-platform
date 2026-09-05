<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_accounts(): void
    {
        Sanctum::actingAs($this->user);

        Account::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_account(): void
    {
        Sanctum::actingAs($this->user);

        $data = [
            'name' => 'بانک ملی',
            'type' => 'bank',
            'currency' => 'IRR',
            'initial_balance' => 1000000,
            'bank_name' => 'بانک ملی ایران',
            'account_number' => '1234567890',
        ];

        $response = $this->postJson('/api/accounts', $data);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'بانک ملی')
            ->assertJsonPath('data.type', 'bank')
            ->assertJsonPath('data.currency', 'IRR')
            ->assertJsonPath('data.current_balance', 1000000);

        $this->assertDatabaseHas('accounts', [
            'name' => 'بانک ملی',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_update_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'حساب اولیه',
        ]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'name' => 'حساب به‌روز شده',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'حساب به‌روز شده');
    }

    public function test_can_delete_empty_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_cannot_delete_account_with_transactions(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $account->transactions()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 100000,
            'currency' => 'IRR',
            'transaction_date' => now(),
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot delete account: has existing transactions');
    }

    public function test_can_get_account_balance(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 500000,
            'currency' => 'IRR',
        ]);

        $response = $this->getJson("/api/accounts/{$account->id}/balance");

        $response->assertOk()
            ->assertJsonPath('data.current_balance', 500000)
            ->assertJsonPath('data.currency', 'IRR');
    }

    public function test_cannot_access_other_users_accounts(): void
    {
        $otherUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/accounts/{$account->id}");

        $response->assertNotFound();
    }
}
