<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Master\Partner;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class JournalController extends Controller
{
    public function __construct(private readonly JournalService $journals) {}

    public function index(Request $request): View
    {
        return view('accounting.journals.index', [
            'journals' => Journal::query()
                ->with('creator:id,name')
                ->withCount('lines')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('accounting.journals.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.partner_id' => ['nullable', 'exists:partners,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $journal = $this->journals->post(
                array_map(fn ($line) => [
                    'account_id' => (int) $line['account_id'],
                    'partner_id' => $line['partner_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                ], $data['lines']),
                $data['date'],
                'general',
                $data['description'],
                null,
                $data['reference'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('journals.show', $journal)
            ->with('success', "Jurnal {$journal->journal_no} berhasil diposting.");
    }

    public function show(Journal $journal): View
    {
        $journal->load('lines.account', 'lines.partner', 'creator', 'reversal');

        return view('accounting.journals.show', ['journal' => $journal]);
    }

    public function reverse(Journal $journal): RedirectResponse
    {
        try {
            $journal->load('lines');
            $reversal = $this->journals->reverse($journal);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('journals.show', $reversal)
            ->with('success', "Jurnal {$journal->journal_no} dibalik dengan {$reversal->journal_no}.");
    }

    public function destroy(Journal $journal): RedirectResponse
    {
        if ($journal->source_type) {
            return back()->with('error', 'Jurnal otomatis tidak dapat dihapus. Batalkan dokumen sumbernya.');
        }

        if ($journal->status === 'posted') {
            return back()->with('error', 'Jurnal yang sudah diposting harus dibalik, bukan dihapus.');
        }

        $number = $journal->journal_no;
        $journal->delete();

        return redirect()->route('journals.index')->with('success', "Jurnal {$number} berhasil dihapus.");
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::postable()->orderBy('code')->get()
                ->map(fn (Account $a) => ['id' => $a->id, 'label' => $a->label()])->values(),
            'partners' => Partner::active()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
