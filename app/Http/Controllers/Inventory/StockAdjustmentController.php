<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockAdjustment;
use App\Models\Master\Product;
use App\Models\Master\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\Posting\InventoryPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Stock opname / manual correction. The form is seeded with the current system
 * quantity per product so the user only types what was actually counted.
 */
class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryPostingService $posting,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): View
    {
        return view('inventory.adjustments.index', [
            'documents' => StockAdjustment::query()
                ->with('warehouse:id,name')
                ->withCount('items')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('inventory.adjustments.form', $this->formData(null, (int) $request->query('warehouse_id', Warehouse::defaultId())));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $adjustment = DB::transaction(function () use ($data) {
            $adjustment = StockAdjustment::create(array_merge(Arr::except($data, ['items']), [
                'adjustment_no' => $this->numbers->next('stock_adjustment', $data['date']),
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            $adjustment->items()->createMany($this->mapRows($data['items'], (int) $data['warehouse_id']));

            return $adjustment;
        });

        return redirect()->route('stock-adjustments.show', $adjustment)
            ->with('success', "Penyesuaian {$adjustment->adjustment_no} dibuat sebagai draft.");
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load('items.product.uom', 'warehouse', 'creator', 'journals');

        return view('inventory.adjustments.show', ['document' => $stockAdjustment]);
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        abort_unless($stockAdjustment->isEditable(), 403, 'Penyesuaian yang sudah diposting tidak dapat diubah.');

        $stockAdjustment->load('items.product.uom');

        return view('inventory.adjustments.form', $this->formData($stockAdjustment, $stockAdjustment->warehouse_id));
    }

    public function update(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        abort_unless($stockAdjustment->isEditable(), 403);

        $data = $request->validate($this->rules());

        DB::transaction(function () use ($stockAdjustment, $data) {
            $stockAdjustment->update(Arr::except($data, ['items']));
            $stockAdjustment->items()->delete();
            $stockAdjustment->items()->createMany($this->mapRows($data['items'], (int) $data['warehouse_id']));
        });

        return redirect()->route('stock-adjustments.show', $stockAdjustment)
            ->with('success', "Penyesuaian {$stockAdjustment->adjustment_no} berhasil diperbarui.");
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        abort_unless($stockAdjustment->isEditable(), 403);

        $number = $stockAdjustment->adjustment_no;
        $stockAdjustment->delete();

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Penyesuaian {$number} berhasil dihapus.");
    }

    public function post(StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->posting->postAdjustment($stockAdjustment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penyesuaian {$stockAdjustment->adjustment_no} diposting.");
    }

    public function cancel(StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->posting->cancelAdjustment($stockAdjustment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penyesuaian {$stockAdjustment->adjustment_no} dibatalkan.");
    }

    /** Current system quantity + cost for a product in a warehouse. */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        $productId = (int) $request->query('product_id');
        $warehouseId = (int) $request->query('warehouse_id');

        return response()->json([
            'system_qty' => $this->inventory->onHand($productId, $warehouseId),
            'unit_cost' => $this->inventory->currentCost($productId, $warehouseId),
        ]);
    }

    /** Pre-fills the form with every product that currently has stock. */
    public function loadStock(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->query('warehouse_id');

        $rows = Stock::query()
            ->with('product:id,sku,name,uom_id', 'product.uom:id,code')
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '!=', 0)
            ->get()
            ->map(fn (Stock $s) => [
                'product_id' => $s->product_id,
                'system_qty' => (float) $s->quantity,
                'actual_qty' => (float) $s->quantity,
                'unit_cost' => (float) $s->avg_cost,
                'uom' => $s->product?->uom?->code ?? '',
                'label' => $s->product?->label() ?? '',
            ])
            ->values();

        return response()->json($rows);
    }

    private function formData(?StockAdjustment $adjustment, ?int $warehouseId): array
    {
        return [
            'document' => $adjustment,
            'rows' => $adjustment
                ? $adjustment->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'system_qty' => (float) $i->system_qty,
                    'actual_qty' => (float) $i->actual_qty,
                    'unit_cost' => (float) $i->unit_cost,
                    'notes' => $i->notes,
                    'uom' => $i->product?->uom?->code ?? '',
                    'label' => $i->product?->label() ?? '',
                ])->all()
                : [],
            'products' => Product::optionsPayload(),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'warehouseId' => $warehouseId,
            'nextNumber' => $adjustment?->adjustment_no ?? $this->numbers->peek('stock_adjustment'),
            'action' => $adjustment ? route('stock-adjustments.update', $adjustment) : route('stock-adjustments.store'),
            'method' => $adjustment ? 'PUT' : 'POST',
        ];
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'reason' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.actual_qty' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** System quantity is read server-side so a stale browser value cannot skew the difference. */
    private function mapRows(array $items, int $warehouseId): array
    {
        return array_map(function (array $row) use ($warehouseId) {
            $systemQty = $this->inventory->onHand((int) $row['product_id'], $warehouseId);
            $actualQty = round((float) $row['actual_qty'], 4);

            return [
                'product_id' => $row['product_id'],
                'system_qty' => $systemQty,
                'actual_qty' => $actualQty,
                'difference' => round($actualQty - $systemQty, 4),
                'unit_cost' => $this->inventory->currentCost((int) $row['product_id'], $warehouseId),
                'notes' => $row['notes'] ?? null,
            ];
        }, $items);
    }
}
