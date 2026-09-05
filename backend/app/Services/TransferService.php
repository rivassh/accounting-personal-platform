<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function transfer(
        int $userId,
        int $fromAccountId,
        int $toAccountId,
        int $amount,
        string $currency,
        string $transactionDate,
        ?string $description = null,
        ?string $reference = null,
        ?array $metadata = null
    ): Transaction {
        return DB::transaction(function () use (
            $userId, $fromAccountId, $toAccountId, $amount, $currency,
            $transactionDate, $description, $reference, $metadata
        ) {
            $fromAccount = Account::where('id', $fromAccountId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $toAccount = Account::where('id', $toAccountId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateTransfer($fromAccount, $toAccount, $amount, $currency);

            $transaction = Transaction::create([
                'user_id' => $userId,
                'account_id' => $fromAccountId,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $currency,
                'transaction_date' => $transactionDate,
                'description' => $description,
                'reference' => $reference,
                'metadata' => array_merge($metadata ?? [], [
                    'transfer_to_account_id' => $toAccountId,
                    'transfer_to_account_name' => $toAccount->name,
                ]),
            ]);

            $fromAccount->decrement('current_balance', $amount);
            $toAccount->increment('current_balance', $amount);

            return $transaction->load('account');
        });
    }

    protected function validateTransfer(Account $fromAccount, Account $toAccount, int $amount, string $currency): void
    {
        if ($fromAccount->id === $toAccount->id) {
            throw new \InvalidArgumentException("Cannot transfer to the same account");
        }

        if ($fromAccount->currency !== $currency) {
            throw new \InvalidArgumentException("Source account currency ({$fromAccount->currency}) does not match transfer currency ({$currency})");
        }

        if ($toAccount->currency !== $currency) {
            throw new \InvalidArgumentException("Destination account currency ({$toAccount->currency}) does not match transfer currency ({$currency})");
        }

        if ($fromAccount->current_balance < $amount) {
            throw new \InvalidArgumentException("Insufficient balance in source account");
        }

        if (!$fromAccount->is_active) {
            throw new \InvalidArgumentException("Source account is not active");
        }

        if (!$toAccount->is_active) {
            throw new \InvalidArgumentException("Destination account is not active");
        }
    }
}
