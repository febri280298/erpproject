<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalLine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingReportController extends Controller
{
    /** General ledger for one account over a period. */
    public function ledger(Request $request): View
    {
        [$from, $to] = $this->period($request);
        $accountId = $request->query('account_id');
        $account = $accountId ? Account::find($accountId) : null;

        $lines = collect();
        $opening = 0.0;

        if ($account) {
            $opening = $account->balance(null, date('Y-m-d', strtotime($from.' -1 day')));

            $lines = JournalLine::query()
                ->with('journal:id,journal_no,date,description,reference', 'partner:id,name')
                ->where('account_id', $account->id)
                ->whereHas('journal', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))
                ->get()
                ->sortBy(fn ($line) => [$line->journal->date->toDateString(), $line->id])
                ->values();
        }

        return view('accounting.reports.ledger', [
            'account' => $account,
            'accounts' => Account::postable()->orderBy('code')->get()
                ->mapWithKeys(fn (Account $a) => [$a->id => $a->label()]),
            'lines' => $lines,
            'opening' => $opening,
            'filters' => compact('from', 'to', 'accountId'),
        ]);
    }

    /** Trial balance: movement debit/credit per account plus the closing balance. */
    public function trialBalance(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $totals = Account::movementTotals($from, $to);
        $accounts = Account::query()->postable()->orderBy('code')->get();

        $rows = $accounts->map(function (Account $account) use ($totals, $from) {
            $movement = $totals->get($account->id);
            $debit = (float) ($movement->debit ?? 0);
            $credit = (float) ($movement->credit ?? 0);

            $opening = $account->balance(null, date('Y-m-d', strtotime($from.' -1 day')));
            $net = $account->isDebitNormal() ? $debit - $credit : $credit - $debit;
            $closing = $opening + $net;

            return [
                'account' => $account,
                'opening' => $opening,
                'debit' => $debit,
                'credit' => $credit,
                'closing' => $closing,
            ];
        })->filter(fn ($row) => abs($row['opening']) > 0.001 || $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return view('accounting.reports.trial-balance', [
            'rows' => $rows,
            'filters' => compact('from', 'to'),
            'totalDebit' => $rows->sum('debit'),
            'totalCredit' => $rows->sum('credit'),
        ]);
    }

    /** Profit & loss for a period. */
    public function incomeStatement(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $totals = Account::movementTotals($from, $to);

        $collect = function (string $type) use ($totals) {
            return Account::query()->postable()->where('type', $type)->orderBy('code')->get()
                ->map(function (Account $account) use ($totals) {
                    $movement = $totals->get($account->id);
                    $debit = (float) ($movement->debit ?? 0);
                    $credit = (float) ($movement->credit ?? 0);

                    return [
                        'account' => $account,
                        'amount' => $account->isDebitNormal() ? $debit - $credit : $credit - $debit,
                    ];
                })
                ->filter(fn ($row) => abs($row['amount']) > 0.001)
                ->values();
        };

        $revenues = $collect('revenue');
        $expenses = $collect('expense');

        return view('accounting.reports.income-statement', [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totalRevenue' => $revenues->sum('amount'),
            'totalExpense' => $expenses->sum('amount'),
            'netIncome' => $revenues->sum('amount') - $expenses->sum('amount'),
            'filters' => compact('from', 'to'),
        ]);
    }

    /** Balance sheet as at a date, with the running profit folded into equity. */
    public function balanceSheet(Request $request): View
    {
        $asOf = $request->query('as_of', now()->toDateString());

        $totals = Account::movementTotals(null, $asOf);

        $collect = function (string $type) use ($totals) {
            return Account::query()->postable()->where('type', $type)->orderBy('code')->get()
                ->map(function (Account $account) use ($totals) {
                    $movement = $totals->get($account->id);
                    $debit = (float) ($movement->debit ?? 0);
                    $credit = (float) ($movement->credit ?? 0);
                    $net = $account->isDebitNormal() ? $debit - $credit : $credit - $debit;

                    return ['account' => $account, 'amount' => $net + (float) $account->opening_balance];
                })
                ->filter(fn ($row) => abs($row['amount']) > 0.001)
                ->values();
        };

        $assets = $collect('asset');
        $liabilities = $collect('liability');
        $equity = $collect('equity');
        $revenues = $collect('revenue');
        $expenses = $collect('expense');

        $netIncome = $revenues->sum('amount') - $expenses->sum('amount');

        return view('accounting.reports.balance-sheet', [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $assets->sum('amount'),
            'totalLiabilities' => $liabilities->sum('amount'),
            'totalEquity' => $equity->sum('amount') + $netIncome,
            'netIncome' => $netIncome,
            'asOf' => $asOf,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        return [
            $request->query('from', now()->startOfMonth()->toDateString()),
            $request->query('to', now()->toDateString()),
        ];
    }
}
