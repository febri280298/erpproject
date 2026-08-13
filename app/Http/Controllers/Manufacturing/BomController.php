<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\Bom;
use App\Models\Master\Product;
use App\Models\Master\Uom;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BomController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): View
    {
        return view('manufacturing.boms.index', [
            'boms' => Bom::query()
                ->with('product:id,sku,name', 'uom:id,code')
                ->withCount('items')
                ->search($request->query('q'))
                ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
                ->orderBy('bom_no')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('manufacturing.boms.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $bom = DB::transaction(function () use ($data) {
            $bom = Bom::create(array_merge(Arr::except($data, ['items']), [
                'bom_no' => $this->numbers->next('bom', now()->toDateString()),
            ]));

            $bom->items()->createMany($this->mapRows($data['items']));

            return $bom;
        });

        return redirect()->route('boms.show', $bom)->with('success', "BOM {$bom->bom_no} berhasil dibuat.");
    }

    public function show(Bom $bom): View
    {
        $bom->load('items.product.uom', 'product.uom', 'uom');

        return view('manufacturing.boms.show', ['bom' => $bom]);
    }

    public function edit(Bom $bom): View
    {
        $bom->load('items.product.uom');

        return view('manufacturing.boms.form', $this->formData($bom));
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($bom, $data) {
            $bom->update(Arr::except($data, ['items']));
            $bom->items()->delete();
            $bom->items()->createMany($this->mapRows($data['items']));
        });

        return redirect()->route('boms.show', $bom)->with('success', "BOM {$bom->bom_no} berhasil diperbarui.");
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $number = $bom->bom_no;
        $bom->delete();

        return redirect()->route('boms.index')->with('success', "BOM {$number} berhasil dihapus.");
    }

    private function formData(?Bom $bom): array
    {
        return [
            'bom' => $bom,
            'rows' => $bom
                ? $bom->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'quantity' => (float) $i->quantity,
                    'waste_percent' => (float) $i->waste_percent,
                    'description' => $i->notes,
                    'uom' => $i->product?->uom?->code ?? '',
                ])->all()
                : [],
            'products' => Product::optionsPayload(),
            'uoms' => Uom::active()->orderBy('code')->pluck('name', 'id'),
            'nextNumber' => $bom?->bom_no ?? $this->numbers->peek('bom'),
            'action' => $bom ? route('boms.update', $bom) : route('boms.store'),
            'method' => $bom ? 'PUT' : 'POST',
        ];
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'uom_id' => ['nullable', 'exists:uoms,id'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function mapRows(array $items): array
    {
        return array_map(fn (array $row) => [
            'product_id' => $row['product_id'],
            'quantity' => round((float) $row['quantity'], 4),
            'waste_percent' => round((float) ($row['waste_percent'] ?? 0), 2),
            'notes' => $row['description'] ?? null,
        ], $items);
    }
}
