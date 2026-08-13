<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\PaymentTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $partners = Partner::query()
            ->with('paymentTerm:id,name')
            ->search($request->query('q'))
            ->when($request->filled('type'), function ($q) use ($request) {
                return match ($request->query('type')) {
                    'customer' => $q->customers(),
                    'supplier' => $q->suppliers(),
                    default => $q,
                };
            })
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('master.partners.index', ['partners' => $partners]);
    }

    public function create(Request $request): View
    {
        return view('master.partners.form', [
            'partner' => new Partner([
                'type' => $request->query('type', Partner::TYPE_CUSTOMER),
                'is_active' => true,
            ]),
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $partner = Partner::create($this->validated($request));

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', "Mitra \"{$partner->name}\" berhasil ditambahkan.");
    }

    public function show(Partner $partner): View
    {
        $partner->load('paymentTerm');

        return view('master.partners.show', [
            'partner' => $partner,
            'salesOrders' => $partner->salesOrders()->latest('date')->limit(10)->get(),
            'purchaseOrders' => $partner->purchaseOrders()->latest('date')->limit(10)->get(),
            'openReceivables' => $partner->salesInvoices()->unpaid()->latest('date')->get(),
            'openPayables' => $partner->purchaseInvoices()->unpaid()->latest('date')->get(),
        ]);
    }

    public function edit(Partner $partner): View
    {
        return view('master.partners.form', [
            'partner' => $partner,
            'paymentTerms' => PaymentTerm::active()->orderBy('days')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $partner->update($this->validated($request, $partner));

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', "Mitra \"{$partner->name}\" berhasil diperbarui.");
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        $hasTransactions = $partner->salesOrders()->exists()
            || $partner->purchaseOrders()->exists()
            || $partner->salesInvoices()->exists()
            || $partner->purchaseInvoices()->exists();

        if ($hasTransactions) {
            return back()->with('error', 'Mitra ini sudah memiliki transaksi. Nonaktifkan saja agar riwayat tetap utuh.');
        }

        $name = $partner->name;
        $partner->delete();

        return redirect()->route('partners.index')->with('success', "Mitra \"{$name}\" berhasil dihapus.");
    }

    private function validated(Request $request, ?Partner $partner = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('partners', 'code')->ignore($partner?->id)],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in([Partner::TYPE_CUSTOMER, Partner::TYPE_SUPPLIER, Partner::TYPE_BOTH])],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'payment_term_id' => ['nullable', 'exists:payment_terms,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
