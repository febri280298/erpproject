<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Master\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\Posting\ManufacturingPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ManufacturingPostingService $posting,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): View
    {
        return view('manufacturing.orders.index', [
            'documents' => ProductionOrder::query()
                ->with('product:id,sku,name', 'warehouse:id,name', 'bom:id,bom_no')
                ->filter($request->query())
                ->latest('date')->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('manufacturing.orders.form', [
            'document' => null,
            'boms' => Bom::query()->where('is_active', true)->with('product:id,sku,name')->orderBy('bom_no')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'defaultWarehouse' => Warehouse::defaultId(),
            'nextNumber' => $this->numbers->peek('production_order'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'bom_id' => ['required', 'exists:boms,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $bom = Bom::with('items.product')->findOrFail($data['bom_id']);

        $order = DB::transaction(function () use ($data, $bom) {
            // Component requirements scale with how many batches are being made.
            $batches = (float) $data['quantity'] / max((float) $bom->quantity, 0.0001);

            $order = ProductionOrder::create([
                'order_no' => $this->numbers->next('production_order', $data['date']),
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? null,
                'bom_id' => $bom->id,
                'product_id' => $bom->product_id,
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $order->items()->createMany($bom->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'planned_qty' => round($item->effectiveQty() * $batches, 4),
                'consumed_qty' => 0,
                'unit_cost' => $this->inventory->currentCost($item->product_id, (int) $data['warehouse_id']),
            ])->all());

            return $order;
        });

        return redirect()->route('production-orders.show', $order)
            ->with('success', "Perintah produksi {$order->order_no} dibuat.");
    }

    public function show(ProductionOrder $productionOrder): View
    {
        $productionOrder->load('items.product.uom', 'product.uom', 'warehouse', 'bom', 'creator', 'journals');

        return view('manufacturing.orders.show', [
            'document' => $productionOrder,
            'availability' => $productionOrder->items->mapWithKeys(fn ($item) => [
                $item->id => $this->inventory->onHand($item->product_id, $productionOrder->warehouse_id),
            ]),
        ]);
    }

    public function destroy(ProductionOrder $productionOrder): RedirectResponse
    {
        abort_unless($productionOrder->isDraft(), 403, 'Hanya perintah produksi draft yang dapat dihapus.');

        $number = $productionOrder->order_no;
        $productionOrder->delete();

        return redirect()->route('production-orders.index')
            ->with('success', "Perintah produksi {$number} berhasil dihapus.");
    }

    public function release(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->posting->release($productionOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Perintah produksi {$productionOrder->order_no} dirilis.");
    }

    public function complete(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $data = $request->validate([
            'produced_qty' => ['required', 'numeric', 'min:0.0001'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'consumed' => ['nullable', 'array'],
            'consumed.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            // Persist any per-component override before the posting service reads them.
            foreach ($data['consumed'] ?? [] as $itemId => $qty) {
                $productionOrder->items()->where('id', $itemId)->update(['consumed_qty' => round((float) $qty, 4)]);
            }

            $this->posting->complete(
                $productionOrder->refresh(),
                (float) $data['produced_qty'],
                (float) ($data['overhead_cost'] ?? 0),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Produksi {$productionOrder->order_no} selesai dan stok diperbarui.");
    }

    public function cancel(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->posting->cancel($productionOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Perintah produksi {$productionOrder->order_no} dibatalkan.");
    }

    /** BOM detail for the create form (components + suggested output quantity). */
    public function bomDetail(Bom $bom): JsonResponse
    {
        $bom->load('items.product.uom', 'product');

        return response()->json([
            'product' => $bom->product?->label(),
            'quantity' => (float) $bom->quantity,
            'items' => $bom->items->map(fn ($i) => [
                'product' => $i->product?->label(),
                'quantity' => $i->effectiveQty(),
                'uom' => $i->product?->uom?->code ?? '',
            ])->values(),
        ]);
    }
}
