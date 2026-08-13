<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Hr\Department;
use App\Models\Master\Product;
use App\Models\Purchasing\PurchaseRequisition;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Purchase requisitions carry quantities and an indicative price only — no tax
 * or totals — so they do not share LineItemDocumentController.
 */
class PurchaseRequisitionController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): View
    {
        return view('purchasing.requisitions.index', [
            'documents' => PurchaseRequisition::query()
                ->with(['department:id,name', 'requester:id,name'])
                ->withCount('items')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('purchasing.requisitions.form', [
            'document' => null,
            'rows' => [],
            'products' => Product::optionsPayload(),
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
            'nextNumber' => $this->numbers->peek('purchase_requisition'),
            'action' => route('purchase-requisitions.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $document = DB::transaction(function () use ($data) {
            $document = PurchaseRequisition::create(array_merge(Arr::except($data, ['items']), [
                'pr_no' => $this->numbers->next('purchase_requisition', $data['date']),
                'status' => 'draft',
                'requested_by' => Auth::id(),
            ]));

            $document->items()->createMany($this->mapRows($data['items']));

            return $document;
        });

        return redirect()
            ->route('purchase-requisitions.show', $document)
            ->with('success', "Permintaan {$document->pr_no} berhasil dibuat.");
    }

    public function show(PurchaseRequisition $purchaseRequisition): View
    {
        $purchaseRequisition->load('items.product.uom', 'department', 'requester', 'approver', 'purchaseOrders');

        return view('purchasing.requisitions.show', ['document' => $purchaseRequisition]);
    }

    public function edit(PurchaseRequisition $purchaseRequisition): View
    {
        abort_unless($purchaseRequisition->isEditable(), 403, 'Permintaan yang sudah diajukan tidak dapat diubah.');

        $purchaseRequisition->load('items.product.uom');

        return view('purchasing.requisitions.form', [
            'document' => $purchaseRequisition,
            'rows' => $purchaseRequisition->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'uom' => $i->product?->uom?->code ?? '',
            ])->all(),
            'products' => Product::optionsPayload(),
            'departments' => Department::active()->orderBy('name')->pluck('name', 'id'),
            'nextNumber' => $purchaseRequisition->pr_no,
            'action' => route('purchase-requisitions.update', $purchaseRequisition),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        abort_unless($purchaseRequisition->isEditable(), 403);

        $data = $request->validate($this->rules());

        DB::transaction(function () use ($purchaseRequisition, $data) {
            $purchaseRequisition->update(Arr::except($data, ['items']));
            $purchaseRequisition->items()->delete();
            $purchaseRequisition->items()->createMany($this->mapRows($data['items']));
        });

        return redirect()
            ->route('purchase-requisitions.show', $purchaseRequisition)
            ->with('success', "Permintaan {$purchaseRequisition->pr_no} berhasil diperbarui.");
    }

    public function destroy(PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        abort_unless($purchaseRequisition->isEditable(), 403);

        $number = $purchaseRequisition->pr_no;
        $purchaseRequisition->delete();

        return redirect()->route('purchase-requisitions.index')
            ->with('success', "Permintaan {$number} berhasil dihapus.");
    }

    public function submit(PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        if (! $purchaseRequisition->isDraft()) {
            return back()->with('error', 'Permintaan ini sudah diajukan.');
        }

        $purchaseRequisition->forceFill(['status' => 'submitted'])->save();
        $purchaseRequisition->recordActivity('submitted', "PR {$purchaseRequisition->pr_no} diajukan");

        return back()->with('success', 'Permintaan berhasil diajukan untuk persetujuan.');
    }

    public function approve(PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        if ($purchaseRequisition->status !== 'submitted') {
            return back()->with('error', 'Hanya permintaan berstatus diajukan yang dapat disetujui.');
        }

        $purchaseRequisition->forceFill([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])->save();

        $purchaseRequisition->recordActivity('approved', "PR {$purchaseRequisition->pr_no} disetujui");

        return back()->with('success', 'Permintaan disetujui. Anda dapat membuat pesanan pembelian dari dokumen ini.');
    }

    public function reject(Request $request, PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:255']]);

        if ($purchaseRequisition->status !== 'submitted') {
            return back()->with('error', 'Hanya permintaan berstatus diajukan yang dapat ditolak.');
        }

        $purchaseRequisition->forceFill([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'reject_reason' => $data['reject_reason'],
        ])->save();

        $purchaseRequisition->recordActivity('rejected', "PR {$purchaseRequisition->pr_no} ditolak");

        return back()->with('success', 'Permintaan ditolak.');
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:date'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function mapRows(array $items): array
    {
        return array_map(fn (array $row) => [
            'product_id' => $row['product_id'],
            'description' => $row['description'] ?? null,
            'quantity' => round((float) $row['quantity'], 4),
            'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
        ], $items);
    }
}
