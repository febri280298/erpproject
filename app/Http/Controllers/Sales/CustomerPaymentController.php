<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\PaymentDocumentController;
use App\Models\Sales\CustomerPayment;
use App\Models\Sales\SalesInvoice;
use App\Services\DocumentNumberService;
use App\Services\Posting\SalesPostingService;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentController extends PaymentDocumentController
{
    protected string $model = CustomerPayment::class;

    protected string $invoiceModel = SalesInvoice::class;

    protected string $routeName = 'customer-payments';

    protected string $title = 'Penerimaan Pembayaran';

    protected string $permission = 'customer-payment';

    protected string $numberModule = 'customer_payment';

    protected string $viewPath = 'sales.payments';

    protected string $partnerScope = 'customers';

    protected string $partnerRelation = 'customer';

    public function __construct(
        DocumentNumberService $numbers,
        private readonly SalesPostingService $posting,
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
        return 'sales_invoice_id';
    }
}
