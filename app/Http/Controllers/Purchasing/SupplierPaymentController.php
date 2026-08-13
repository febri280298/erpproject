<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Concerns\PaymentDocumentController;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\SupplierPayment;
use App\Services\DocumentNumberService;
use App\Services\Posting\PurchasingPostingService;
use Illuminate\Database\Eloquent\Model;

class SupplierPaymentController extends PaymentDocumentController
{
    protected string $model = SupplierPayment::class;

    protected string $invoiceModel = PurchaseInvoice::class;

    protected string $routeName = 'supplier-payments';

    protected string $title = 'Pembayaran Pemasok';

    protected string $permission = 'supplier-payment';

    protected string $numberModule = 'supplier_payment';

    protected string $viewPath = 'purchasing.payments';

    protected string $partnerScope = 'suppliers';

    protected string $partnerRelation = 'supplier';

    public function __construct(
        DocumentNumberService $numbers,
        private readonly PurchasingPostingService $posting,
    ) {
        parent::__construct($numbers);
    }

    protected function postDocument(Model $payment): void
    {
        $this->posting->postPayment($payment);
    }

    protected function cancelDocument(Model $payment): void
    {
        $this->posting->cancelPayment($payment);
    }

    protected function invoiceForeignKey(): string
    {
        return 'purchase_invoice_id';
    }
}
