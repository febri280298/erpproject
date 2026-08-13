<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockTransfer;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\Posting\InventoryPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryPostingService $posting,
    ) {}

    public function index(Request $request): View
    {
        return view('inventory.transfers.index', [
            'documents' => StockTransfer::query()
                ->with(['fromWarehouse:id,name', 'toWarehouse:id,name'])
                ->withCount('items')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('inventory.transfers.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $transfer = DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create(array_merge(Arr::except($data, ['items']), [
                'transfer_no' => $this->numbers->next('stock_transfer', $data['date']),
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            $transfer->items()->createMany($this->mapRows($data['items']));

            return $transfer;
        });

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->transfer_no} dibuat sebagai draft.");
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $stockTransfer->load('items.product.uom', 'fromWarehouse', 'toWarehouse', 'creator');

        return view('inventory.transfers.show', ['document' => $stockTransfer]);
    }

    public function edit(StockTransfer $stockTransfer): View
    {
        abort_unless($stockTransfer->isEditable(), 403, 'Transfer yang sudah diposting tidak dapat diubah.');

        $stockTransfer->load('items.product.uom');

        return view('inventory.transfers.form', $this->formData($stockTransfer));
    }

    public function update(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        abort_unless($stockTransfer->isEditable(), 403);

        $data = $request->validate($this->rules());

        DB::transaction(function () use ($stockTransfer, $data) {
            $stockTransfer->update(Arr::except($data, ['items']));
            $stockTransfer->items()->delete();
            $stockTransfer->items()->createMany($this->mapRows($data['items']));
        });

        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('success', "Transfer {$stockTransfer->transfer_no} berhasil diperbarui.");
    }

    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        abort_unless($stockTransfer->isEditable(), 403);

        $number = $stockTransfer->transfer_no;
        $stockTransfer->delete();

        return redirect()->route('stock-transfers.index')
            ->with('success', "Transfer {$number} berhasil dihapus.");
    }

    public function post(StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->posting->postTransfer($stockTransfer);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transfer {$stockTransfer->transfer_no} diposting.");
    }

    public function cancel(StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->posting->cancelTransfer($stockTransfer);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Transfer {$stockTransfer->transfer_no} dibatalkan.");
    }

    private function formData(?StockTransfer $transfer): array
    {
        return [
            'document' => $transfer,
            'rows' => $transfer
                ? $transfer->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'quantity' => (float) $i->quantity,
                    'description' => $i->notes,
                    'uom' => $i->product?->uom?->code ?? '',
                ])->all()
                : [],
            'products' => Product::optionsPayload(),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'nextNumber' => $transfer?->transfer_no ?? $this->numbers->peek('stock_transfer'),
            'action' => $transfer ? route('stock-transfers.update', $transfer) : route('stock-transfers.store'),
            'method' => $transfer ? 'PUT' : 'POST',
        ];
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function mapRows(array $items): array
    {
        return array_map(fn (array $row) => [
            'product_id' => $row['product_id'],
            'quantity' => round((float) $row['quantity'], 4),
            'notes' => $row['description'] ?? null,
        ], $items);
    }
}
