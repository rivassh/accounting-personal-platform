<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BalanceCalculationService
{
    public function calculateBalance(int $accountId): int
    {
        $account = Account::findOrFail($accountId);

        return $account->initial_balance + $this->getTransactionSum($accountId);
    }

    public function getTransactionSum(int $accountId): int
    {
        $income = Transaction::where('account_id', $accountId)
            ->where('type', 'income')
            ->sum('amount');

        $expense = Transaction::where('account_id', $accountId)
            ->where('type', 'expense')
            ->sum('amount');

        return $income - $expense;
    }

    public function recalculateAllBalances(): void
    {
        Account::chunkById(100, function ($accounts) {
            foreach ($accounts as $account) {
                $calculatedBalance = $account->initial_balance + $this->getTransactionSum($account->id);
                $account->update(['current_balance' => $calculatedBalance]);
            }
        });
    }

    public function getAccountSummary(int $userId): array
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $summary = [
            'total_balance' => 0,
            'by_currency' => [],
            'by_type' => [],
            'accounts' => [],
        ];

        foreach ($accounts as $account) {
            $balance = $account->current_balance;

            $summary['total_balance'] += $balance;

            $currency = $account->currency;
            if (!isset($summary['by_currency'][$currency])) {
                $summary['by_currency'][$currency] = 0;
            }
            $summary['by_currency'][$currency] += $balance;

            $type = $account->type;
            if (!isset($summary['by_type'][$type])) {
                $summary['by_type'][$type] = 0;
            }
            $summary['by_type'][$type] += $balance;

            $summary['accounts'][] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'balance' => $balance,
            ];
        }

        return $summary;
    }

    public function getMonthlySummary(int $userId, int $year, int $month): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = sprintf('%d-%02d-31', $year, $month);

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        return [
            'year' => $year,
            'month' => $month,
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    public function verifyBalance(int $accountId): array
    {
        $account = Account::findOrFail($accountId);
        $calculatedBalance = $this->calculateBalance($accountId);
        $storedBalance = $account->current_balance;
        $isBalanced = $calculatedBalance === $storedBalance;

        return [
            'account_id' => $accountId,
            'stored_balance' => $storedBalance,
            'calculated_balance' => $calculatedBalance,
            'difference' => $storedBalance - $calculatedBalance,
            'is_balanced' => $isBalanced,
        ];
    }
}
