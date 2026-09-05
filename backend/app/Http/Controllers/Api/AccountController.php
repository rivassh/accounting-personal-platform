<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Account::where('user_id', $request->user()->id);

        if ($request->filled('type')) {
            $query->byType($request->string('type')->value());
        }

        if ($request->filled('currency')) {
            $query->byCurrency($request->string('currency')->value());
        }

        if ($request->boolean('archived')) {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        $accounts = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $account = Account::create([
            'user_id' => $request->user()->id,
            'name' => $request->string('name'),
            'type' => $request->string('type'),
            'currency' => $request->string('currency', 'IRR'),
            'initial_balance' => (int) $request->integer('initial_balance', 0),
            'current_balance' => (int) $request->integer('initial_balance', 0),
            'bank_name' => $request->input('bank_name'),
            'account_number' => $request->input('account_number'),
            'card_number' => $request->input('card_number'),
            'color' => $request->input('color'),
            'icon' => $request->input('icon'),
            'notes' => $request->input('notes'),
        ]);

        return new AccountResource($account);
    }

    public function show(Request $request, Account $account): AccountResource
    {
        $this->authorize($request, $account);
        return new AccountResource($account);
    }

    public function balance(Request $request, Account $account): AccountResource
    {
        $this->authorize($request, $account);
        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $this->authorize($request, $account);

        $data = $request->only([
            'name', 'type', 'currency', 'bank_name',
            'account_number', 'card_number', 'color',
            'icon', 'notes', 'is_active',
        ]);

        if (array_key_exists('initial_balance', $data) && !$request->user()->can('update-balance', $account)) {
            $this->throwBalanceUpdateDenied();
        }

        $account->update($data);

        return new AccountResource($account->fresh());
    }

    public function destroy(Request $request, Account $account)
    {
        $this->authorize($request, $account);

        if ($account->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account: has existing transactions',
            ], 400);
        }

        $account->delete();
        return response()->noContent();
    }

    private function authorize(Request $request, Account $account): void
    {
        if ($account->user_id !== $request->user()->id) {
            abort(404, 'Account not found.');
        }
    }

    private function throwBalanceUpdateDenied(): void
    {
        abort(403, 'Initial balance cannot be updated after the account has transactions.');
    }
}
