<?php

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_categories(): void
    {
        Sanctum::actingAs($this->user);

        Category::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_category(): void
    {
        Sanctum::actingAs($this->user);

        $data = [
            'name' => 'غذا',
            'type' => 'expense',
            'icon' => 'utensils',
            'color' => '#ef4444',
        ];

        $response = $this->postJson('/api/categories', $data);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'غذا')
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.level', 0);
    }

    public function test_can_create_subcategory(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Category::factory()->create([
            'user_id' => $this->user->id,
            'level' => 0,
        ]);

        $data = [
            'name' => 'رستوران',
            'type' => 'expense',
            'parent_id' => $parent->id,
        ];

        $response = $this->postJson('/api/categories', $data);

        $response->assertCreated()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_can_get_category_tree(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
        ]);
        Category::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parent->id,
            'type' => 'expense',
        ]);

        $response = $this->getJson('/api/categories/tree');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['id', 'name', 'children']
            ]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        Sanctum::actingAs($this->user);

        $parent = Category::factory()->create(['user_id' => $this->user->id]);
        Category::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parent->id,
        ]);

        $response = $this->deleteJson("/api/categories/{$parent->id}");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot delete category: has child categories');
    }

    public function test_cannot_delete_category_with_transactions(): void
    {
        Sanctum::actingAs($this->user);

        $account = \App\Models\Account::factory()->create(['user_id' => $this->user->id]);
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        
        $account->transactions()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 100000,
            'currency' => 'IRR',
            'transaction_date' => now(),
        ]);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot delete category: has existing transactions');
    }
}
