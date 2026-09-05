<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BalanceCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected BalanceCalculationService $balanceService;

    public function __construct(BalanceCalculationService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    public function summary()
    {
        $summary = $this->balanceService->getAccountSummary(Auth::id());
        return response()->json($summary);
    }

    public function monthlySummary(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        $summary = $this->balanceService->getMonthlySummary(
            Auth::id(),
            (int) $year,
            (int) $month
        );

        return response()->json($summary);
    }

    public function recentTransactions()
    {
        $transactions = Auth::user()->transactions()
            ->with('account', 'category')
            ->orderBy('transaction_date', 'desc')
            ->limit(10)
            ->get();

        return response()->json($transactions);
    }
}
