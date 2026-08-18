<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Master\Partner;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Shared flow for settling invoices: pick a partner, see their open invoices,
 * allocate an amount to each. Used by both supplier payments and customer
 * receipts, which differ only in direction.
 */
abstract class PaymentDocumentController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var class-string<Model> */
    protected string $invoiceModel;

    protected string $routeName;

    protected string $title;

    protected string $permission;

    protected string $numberModule;

    protected string $viewPath;

    /** `suppliers` or `customers` — the Partner scope for the picker. */
    protected string $partnerScope = 'suppliers';

    protected string $partnerRelation = 'supplier';

    public function __construct(protected readonly DocumentNumberService $numbers) {}

    abstract protected function postDocument(Model $payment): void;

    abstract protected function cancelDocument(Model $payment): void;

    public function index(Request $request): View
    {
        return view("{$this->viewPath}.index", [
            'documents' => $this->model::query()
                ->with([$this->partnerRelation.':id,name', 'account:id,code,name'])
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'partners' => $this->partnerQuery()->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $partnerId = $request->query('partner_id');

        return view("{$this->viewPath}.form", [
            'document' => null,
            'partners' => $this->partnerQuery()->pluck('name', 'id'),
            'accounts' => Account::cashAndBank()->orderBy('code')->get()
                ->mapWithKeys(fn (Account $a) => [$a->id => $a->label()]),
            'partnerId' => $partnerId,
            'openInvoices' => $partnerId ? $this->openInvoices((int) $partnerId) : collect(),
            'nextNumber' => $this->numbers->peek($this->numberModule),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $payment = DB::transaction(function () use ($data) {
                $invoices = $this->openInvoices((int) $data['partner_id'])->keyBy('id');
                $rows = [];
                $total = 0.0;

                foreach ($data['allocations'] as $allocation) {
                    $amount = round((float) ($allocation['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $invoice = $invoices->get((int) $allocation['invoice_id']);
                    if (! $invoice) {
                        throw new RuntimeException('Faktur yang dipilih tidak lagi terbuka.');
                    }

                    if ($amount > $invoice->outstandingAmount() + 0.009) {
                        throw new RuntimeException(sprintf(
                            'Alokasi untuk faktur %s melebihi sisa tagihan (%s).',
                            $invoice->invoice_no,
                            rupiah($invoice->outstandingAmount())
                        ));
                    }

                    $rows[] = [$this->invoiceForeignKey() => $invoice->id, 'amount' => $amount];
                    $total += $amount;
                }

                if ($rows === []) {
                    throw new RuntimeException('Isi minimal satu alokasi pembayaran.');
                }

                $payment = $this->model::create([
                    'payment_no' => $this->numbers->next($this->numberModule, $data['date']),
                    'date' => $data['date'],
                    'partner_id' => $data['partner_id'],
                    'account_id' => $data['account_id'],
                    'amount' => round($total, 2),
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                    'status' => 'draft',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $payment->items()->createMany($rows);

                return $payment;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route("{$this->routeName}.show", $payment)
            ->with('success', "{$this->title} {$payment->payment_no} dibuat sebagai draft.");
    }

    public function show(int $id): View
    {
        $payment = $this->model::with([
            'items.invoice', $this->partnerRelation, 'account', 'creator', 'journals',
        ])->findOrFail($id);

        return view("{$this->viewPath}.show", ['document' => $payment]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $payment = $this->model::findOrFail($id);

        abort_unless($payment->isDraft(), 403, 'Pembayaran yang sudah diposting tidak dapat dihapus.');

        $number = $payment->payment_no;
        $payment->delete();

        return redirect()->route("{$this->routeName}.index")
            ->with('success', "{$this->title} {$number} berhasil dihapus.");
    }

    public function post(int $id): RedirectResponse
    {
        $payment = $this->model::findOrFail($id);

        try {
            $this->postDocument($payment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$this->title} {$payment->payment_no} diposting.");
    }

    public function cancel(int $id): RedirectResponse
    {
        $payment = $this->model::findOrFail($id);

        try {
            $this->cancelDocument($payment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$this->title} {$payment->payment_no} dibatalkan.");
    }

    /** Open invoices for a partner, used both by the form and on submit. */
    protected function openInvoices(int $partnerId)
    {
        return $this->invoiceModel::query()
            ->where('partner_id', $partnerId)
            ->unpaid()
            ->orderBy('due_date')
            ->orderBy('date')
            ->get();
    }

    protected function partnerQuery()
    {
        $scope = $this->partnerScope;

        return Partner::query()->{$scope}()->active()->orderBy('name');
    }

    abstract protected function invoiceForeignKey(): string;
}
