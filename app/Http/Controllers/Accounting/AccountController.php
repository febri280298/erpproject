<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = Account::query()
            ->with('parent:id,code,name')
            ->search($request->query('q'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        // One aggregate query feeds every row's balance column.
        $totals = Account::movementTotals();

        return view('accounting.accounts.index', [
            'accounts' => $accounts,
            'totals' => $totals,
        ]);
    }

    public function create(): View
    {
        return view('accounting.accounts.form', $this->formData(new Account([
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $account = Account::create($this->validated($request));

        return redirect()->route('accounts.index')
            ->with('success', "Akun {$account->code} — {$account->name} berhasil ditambahkan.");
    }

    public function show(Account $account, Request $request): View
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('accounting.accounts.show', [
            'account' => $account,
            'lines' => $account->lines()
                ->with('journal:id,journal_no,date,description,status', 'partner:id,name')
                ->whereHas('journal', fn ($q) => $q->where('status', 'posted')
                    ->whereBetween('date', [$from, $to]))
                ->get()
                ->sortBy(fn ($line) => [$line->journal->date->toDateString(), $line->id])
                ->values(),
            'openingBalance' => $account->balance(null, date('Y-m-d', strtotime($from.' -1 day'))),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function edit(Account $account): View
    {
        return view('accounting.accounts.form', $this->formData($account));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request, $account));

        return redirect()->route('accounts.index')
            ->with('success', "Akun {$account->code} berhasil diperbarui.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->lines()->exists()) {
            return back()->with('error', 'Akun ini sudah memiliki transaksi. Nonaktifkan saja.');
        }

        if ($account->children()->exists()) {
            return back()->with('error', 'Akun ini masih memiliki sub-akun.');
        }

        $code = $account->code;
        $account->delete();

        return redirect()->route('accounts.index')->with('success', "Akun {$code} berhasil dihapus.");
    }

    private function formData(Account $account): array
    {
        return [
            'account' => $account,
            'parents' => Account::query()
                ->when($account->exists, fn ($q) => $q->where('id', '!=', $account->id))
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Account $a) => [$a->id => $a->label()]),
        ];
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'subtype' => ['nullable', 'string', 'max:40'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
            'opening_balance' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
