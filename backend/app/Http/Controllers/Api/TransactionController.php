<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaction::where('user_id', $request->user()->id)
            ->with(['account', 'category']);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->value());
        }

        if ($request->filled('account_id')) {
            $query->byAccount($request->integer('account_id'));
        }

        if ($request->filled('category_id')) {
            $query->byCategory($request->integer('category_id'));
        }

        if ($request->filled('currency')) {
            $query->byCurrency($request->string('currency')->value());
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('transaction_date', [
                $request->date('from_date'),
                $request->date('to_date'),
            ]);
        } elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('transaction_date', [
                $request->date('from'),
                $request->date('to'),
            ]);
        } elseif ($request->filled('month')) {
            $query->whereRaw("TO_CHAR(transaction_date, 'YYYY-MM') = ?", [
                $request->string('month')->value()
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where('description', 'ILIKE', '%' . $search . '%');
        }

        $sortField = $request->string('sort', 'transaction_date')->value();
        $sortDir = $request->string('direction', 'desc')->value();
        $query->orderBy($sortField, $sortDir);

        $transactions = $query->paginate($request->integer('per_page', 25));

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $user = $request->user();
        $account = Account::where('id', $request->integer('account_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        $type = $request->string('type')->value();

        try {
            $transaction = match ($type) {
                'income' => $this->service->recordIncome(
                    $user, $account,
                    (int) $request->integer('amount'),
                    $request->string('description')->value(),
                    $request->integer('category_id') ?: null,
                    $request->input('transaction_date'),
                    $request->input('reference'),
                    $request->input('metadata'),
                ),
                'expense' => $this->service->recordExpense(
                    $user, $account,
                    (int) $request->integer('amount'),
                    $request->string('description')->value(),
                    $request->integer('category_id') ?: null,
                    $request->input('transaction_date'),
                    $request->input('reference'),
                    $request->input('metadata'),
                ),
                default => throw new InvalidArgumentException("Invalid transaction type: {$type}"),
            };
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return (new TransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize($request, $transaction);
        $transaction->load(['account', 'category']);
        return new TransactionResource($transaction);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->authorize($request, $transaction);

        abort_if($transaction->isTransfer(), 422, 'Transfers must be updated via the transfer endpoints.');

        if ($request->has('description')) {
            $transaction->update(['description' => $request->string('description')]);
        }

        if ($request->has('category_id')) {
            $transaction->update(['category_id' => $request->integer('category_id') ?: null]);
        }

        if ($request->has('transaction_date')) {
            $transaction->update(['transaction_date' => $request->input('transaction_date')]);
        }

        return (new TransactionResource($transaction->fresh(['account', 'category'])))
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorize($request, $transaction);

        try {
            if ($transaction->isTransfer()) {
                $transferGroup = $transaction->metadata['transfer_group'] ?? null;
                if ($transferGroup) {
                    $this->service->reverseTransfer($transferGroup);
                }
            } else {
                $this->service->reverseTransaction($transaction, 'User deleted');
            }
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->noContent();
    }

    public function transfer(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'from_account_id' => 'required|integer|exists:accounts,id',
            'to_account_id' => 'required|integer|different:from_account_id|exists:accounts,id',
            'amount' => 'required|integer|min:1',
            'description' => 'sometimes|string|max:500',
            'transaction_date' => 'sometimes|date|date_format:Y-m-d',
        ]);

        $source = Account::where('id', $request->integer('from_account_id'))
            ->where('user_id', $user->id)->firstOrFail();
        $destination = Account::where('id', $request->integer('to_account_id'))
            ->where('user_id', $user->id)->firstOrFail();

        try {
            $result = $this->service->transfer(
                $user, $source, $destination,
                (int) $request->integer('amount'),
                $request->string('description', 'Transfer between accounts')->value(),
                $request->input('transaction_date'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return (new TransactionResource($result['outgoing']))
            ->additional([
                'incoming' => new TransactionResource($result['incoming']),
                'transfer_group' => $result['transfer_group'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function statistics(Request $request)
    {
        $month = $request->string('month', now()->format('Y-m'))->value();
        $currency = $request->string('currency')->value();

        $stats = $this->service->getMonthlyStatistics(
            $request->user(),
            $month,
            $currency ?: null
        );

        return response()->json($stats);
    }

    public function recalculateBalance(Request $request, Account $account)
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $newBalance = $this->service->recalculateBalance($account);

        return response()->json([
            'account_id' => $account->id,
            'current_balance' => (int) $newBalance,
        ]);
    }

    private function authorize(Request $request, Transaction $transaction): void
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(404, 'Transaction not found.');
        }
    }
}
