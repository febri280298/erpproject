<?php

namespace App\Http\Controllers\Sales;

use App\Exports\QuotationExport;
use App\Exports\QuotationsExport;
use App\Http\Controllers\Concerns\LineItemDocumentController;
use App\Models\Master\Partner;
use App\Models\Sales\Quotation;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuotationController extends LineItemDocumentController
{
    protected string $model = Quotation::class;

    protected string $routeName = 'quotations';

    protected string $title = 'Penawaran';

    protected string $permission = 'quotation';

    protected string $numberModule = 'quotation';

    protected string $numberField = 'quotation_no';

    protected string $viewPath = 'sales.quotations';

    protected array $indexWith = ['customer:id,name'];

    protected function headerRules(?Model $document = null): array
    {
        return [
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'exists:partners,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function formData(?Model $document = null): array
    {
        return ['customers' => Partner::customers()->active()->orderBy('name')->pluck('name', 'id')];
    }

    protected function indexData(Request $request): array
    {
        return ['customers' => Partner::customers()->orderBy('name')->pluck('name', 'id')];
    }

    protected function showWith(): array
    {
        return ['items.product.uom', 'customer', 'creator', 'salesOrders'];
    }

    /** Draft → sent → accepted/rejected. */
    public function transition(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:sent,accepted,rejected,closed'],
        ]);

        $allowed = [
            'sent' => ['draft'],
            'accepted' => ['draft', 'sent'],
            'rejected' => ['draft', 'sent'],
            'closed' => ['accepted'],
        ];

        if (! in_array($quotation->status, $allowed[$data['status']], true)) {
            return back()->with('error', 'Perubahan status tidak valid untuk penawaran ini.');
        }

        $quotation->forceFill(['status' => $data['status']])->save();
        $quotation->recordActivity($data['status'], "Penawaran {$quotation->quotation_no} → {$data['status']}");

        return back()->with('success', 'Status penawaran diperbarui.');
    }

    public function print(Quotation $quotation): View
    {
        $quotation->load('items.product.uom', 'customer');

        return view("{$this->viewPath}.print", ['document' => $quotation]);
    }

    /** Satu penawaran sebagai lembar kerja Excel, lengkap dengan rumus total. */
    public function excel(Quotation $quotation, SettingService $settings): BinaryFileResponse
    {
        $quotation->load('items.product.uom', 'customer', 'creator');

        return Excel::download(
            new QuotationExport($quotation, $settings),
            'Penawaran-'.str_replace('/', '-', $quotation->quotation_no).'.xlsx',
        );
    }

    /** Daftar penawaran mengikuti filter yang sedang aktif di halaman indeks. */
    public function exportList(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new QuotationsExport($request->query()),
            'Daftar-Penawaran-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
