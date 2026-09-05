<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    public function recordIncome(
        User $user,
        Account $account,
        int $amount,
        string $description,
        ?int $categoryId = null,
        ?string $transactionDate = null,
        ?string $reference = null,
        ?array $metadata = null,
    ): Transaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Income amount must be positive (integer minor units).');
        }

        return DB::transaction(function () use ($user, $account, $amount, $description, $categoryId, $transactionDate, $reference, $metadata) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'type' => 'income',
                'amount' => $amount,
                'currency' => $account->currency,
                'transaction_date' => $transactionDate ?? now()->toDateString(),
                'description' => $description,
                'reference' => $reference ?? $this->generateReference('INC'),
                'metadata' => $metadata,
            ]);

            $account->deposit($amount)->save();

            return $transaction->fresh(['account', 'category']);
        });
    }

    public function recordExpense(
        User $user,
        Account $account,
        int $amount,
        string $description,
        ?int $categoryId = null,
        ?string $transactionDate = null,
        ?string $reference = null,
        ?array $metadata = null,
    ): Transaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Expense amount must be positive (integer minor units).');
        }

        if ($account->current_balance < $amount && $account->type !== 'credit_card') {
            throw new InvalidArgumentException('Insufficient balance for this expense.');
        }

        return DB::transaction(function () use ($user, $account, $amount, $description, $categoryId, $transactionDate, $reference, $metadata) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'type' => 'expense',
                'amount' => $amount,
                'currency' => $account->currency,
                'transaction_date' => $transactionDate ?? now()->toDateString(),
                'description' => $description,
                'reference' => $reference ?? $this->generateReference('EXP'),
                'metadata' => $metadata,
            ]);

            if ($account->type === 'credit_card') {
                $account->addAmount($amount)->save();
            } else {
                $account->withdraw($amount)->save();
            }

            return $transaction->fresh(['account', 'category']);
        });
    }

    public function transfer(
        User $user,
        Account $source,
        Account $destination,
        int $amount,
        string $description = 'Transfer between accounts',
        ?string $transactionDate = null,
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be positive (integer minor units).');
        }

        if ($source->id === $destination->id) {
            throw new InvalidArgumentException('Source and destination accounts must be different.');
        }

        if ($source->user_id !== $user->id || $destination->user_id !== $user->id) {
            throw new InvalidArgumentException('Both accounts must belong to the user.');
        }

        if ($source->currency !== $destination->currency) {
            throw new InvalidArgumentException('Cross-currency transfers require a conversion service. Use transferWithRate().');
        }

        if ($source->type === 'credit_card') {
            throw new InvalidArgumentException('Cannot transfer from a credit card account.');
        }

        if ($source->current_balance < $amount) {
            throw new InvalidArgumentException('Insufficient balance in source account');
        }

        return DB::transaction(function () use ($user, $source, $destination, $amount, $description, $transactionDate) {
            $transferGroup = (string) Str::uuid();
            $date = $transactionDate ?? now()->toDateString();

            $outgoing = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $source->id,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $source->currency,
                'transaction_date' => $date,
                'description' => $description . ' (از ' . $source->name . ')',
                'reference' => $this->generateReference('TRF'),
                'metadata' => [
                    'transfer_group' => $transferGroup,
                    'direction' => 'out',
                    'counterpart_account_id' => $destination->id,
                ],
            ]);

            $incoming = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $destination->id,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $destination->currency,
                'transaction_date' => $date,
                'description' => $description . ' (به ' . $destination->name . ')',
                'reference' => $this->generateReference('TRF'),
                'metadata' => [
                    'transfer_group' => $transferGroup,
                    'direction' => 'in',
                    'counterpart_account_id' => $source->id,
                ],
            ]);

            $source->withdraw($amount)->save();
            $destination->deposit($amount)->save();

            return [
                'outgoing' => $outgoing->fresh(['account']),
                'incoming' => $incoming->fresh(['account']),
                'transfer_group' => $transferGroup,
            ];
        });
    }

    public function transferWithRate(
        User $user,
        Account $source,
        Account $destination,
        int $sourceAmount,
        int $destinationAmount,
        string $description = 'Transfer between accounts',
        ?string $transactionDate = null,
    ): array {
        if ($sourceAmount <= 0 || $destinationAmount <= 0) {
            throw new InvalidArgumentException('Transfer amounts must be positive (integer minor units).');
        }

        if ($source->id === $destination->id) {
            throw new InvalidArgumentException('Source and destination accounts must be different.');
        }

        if ($source->user_id !== $user->id || $destination->user_id !== $user->id) {
            throw new InvalidArgumentException('Both accounts must belong to the user.');
        }

        if ($source->type === 'credit_card') {
            throw new InvalidArgumentException('Cannot transfer from a credit card account.');
        }

        if ($source->current_balance < $sourceAmount) {
            throw new InvalidArgumentException('Insufficient balance in source account for transfer.');
        }

        return DB::transaction(function () use ($user, $source, $destination, $sourceAmount, $destinationAmount, $description, $transactionDate) {
            $transferGroup = (string) Str::uuid();
            $date = $transactionDate ?? now()->toDateString();
            $rate = $destinationAmount > 0 ? round($sourceAmount / $destinationAmount, 10) : 0;

            $outgoing = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $source->id,
                'type' => 'transfer',
                'amount' => $sourceAmount,
                'currency' => $source->currency,
                'transaction_date' => $date,
                'description' => $description . ' (از ' . $source->name . ')',
                'reference' => $this->generateReference('TRF'),
                'metadata' => [
                    'transfer_group' => $transferGroup,
                    'direction' => 'out',
                    'counterpart_account_id' => $destination->id,
                    'exchange_rate' => $rate,
                    'original_amount' => $destinationAmount,
                    'original_currency' => $destination->currency,
                ],
            ]);

            $incoming = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $destination->id,
                'type' => 'transfer',
                'amount' => $destinationAmount,
                'currency' => $destination->currency,
                'transaction_date' => $date,
                'description' => $description . ' (به ' . $destination->name . ')',
                'reference' => $this->generateReference('TRF'),
                'metadata' => [
                    'transfer_group' => $transferGroup,
                    'direction' => 'in',
                    'counterpart_account_id' => $source->id,
                    'exchange_rate' => $rate,
                    'original_amount' => $sourceAmount,
                    'original_currency' => $source->currency,
                ],
            ]);

            $source->withdraw($sourceAmount)->save();
            $destination->deposit($destinationAmount)->save();

            return [
                'outgoing' => $outgoing->fresh(['account']),
                'incoming' => $incoming->fresh(['account']),
                'transfer_group' => $transferGroup,
                'exchange_rate' => $rate,
            ];
        });
    }

    public function reverseTransaction(Transaction $transaction, ?string $reason = null): Transaction
    {
        if ($transaction->isTransfer()) {
            throw new InvalidArgumentException('Transfers must be reversed via reverseTransfer() with the transfer group.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $account = $transaction->account;

            if ($transaction->isIncome()) {
                $account->withdraw($transaction->amount)->save();
                $reversalType = 'expense';
            } else {
                if ($account->type === 'credit_card') {
                    $account->withdraw($transaction->amount)->save();
                } else {
                    $account->deposit($transaction->amount)->save();
                }
                $reversalType = 'income';
            }

            return Transaction::create([
                'user_id' => $transaction->user_id,
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'type' => $reversalType,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'transaction_date' => now()->toDateString(),
                'description' => 'بازگشت: ' . $transaction->description . ($reason ? ' - ' . $reason : ''),
                'reference' => $this->generateReference('REV'),
                'metadata' => [
                    'reversal_of' => $transaction->id,
                    'reason' => $reason,
                ],
            ]);
        });
    }

    public function reverseTransfer(string $transferGroup): bool
    {
        $transactions = Transaction::whereJsonContains('metadata->transfer_group', $transferGroup)->get();

        if ($transactions->isEmpty()) {
            throw new InvalidArgumentException('Transfer group not found.');
        }

        if ($transactions->count() !== 2) {
            throw new InvalidArgumentException('Transfer group does not have exactly two transactions.');
        }

        return DB::transaction(function () use ($transactions, $transferGroup) {
            foreach ($transactions as $transaction) {
                $account = $transaction->account;
                $metadata = $transaction->metadata ?? [];
                $direction = $metadata['direction'] ?? null;

                if ($direction === 'out') {
                    $account->deposit($transaction->amount)->save();
                } else {
                    $account->withdraw($transaction->amount)->save();
                }
            }

            Transaction::whereJsonContains('metadata->transfer_group', $transferGroup)->delete();

            return true;
        });
    }

    public function recalculateBalance(Account $account): int
    {
        return DB::transaction(function () use ($account) {
            $income = Transaction::where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->where('type', 'income')
                ->sum('amount');

            $expense = Transaction::where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->where('type', 'expense')
                ->sum('amount');

            $balance = (int) $account->initial_balance + (int) $income - (int) $expense;
            $account->current_balance = $balance;
            $account->save();

            return $balance;
        });
    }

    public function recalculateAllBalances(User $user): int
    {
        $count = 0;
        Account::where('user_id', $user->id)->each(function (Account $account) use (&$count) {
            $this->recalculateBalance($account);
            $count++;
        });
        return $count;
    }

    public function getBalance(Account $account): int
    {
        return (int) $account->current_balance;
    }

    public function getTotalBalanceByCurrency(User $user, string $currency): int
    {
        return (int) Account::where('user_id', $user->id)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->sum('current_balance');
    }

    public function getMonthlyStatistics(User $user, string $month, ?string $currency = null): array
    {
        $query = Transaction::where('user_id', $user->id)
            ->whereRaw("TO_CHAR(transaction_date, 'YYYY-MM') = ?", [$month]);

        if ($currency) {
            $query->where('currency', $currency);
        }

        $income = (clone $query)->where('type', 'income')->sum('amount');
        $expense = (clone $query)->where('type', 'expense')->sum('amount');
        $transfer = (clone $query)->where('type', 'transfer')->sum('amount');

        return [
            'month' => $month,
            'currency' => $currency,
            'income' => (int) $income,
            'expense' => (int) $expense,
            'transfer' => (int) $transfer,
            'net' => (int) $income - (int) $expense,
        ];
    }

    private function generateReference(string $prefix): string
    {
        return $prefix . '-' . strtoupper(Str::random(10));
    }
}
