<?php

namespace App\Services\Posting;

use App\Models\Manufacturing\ProductionOrder;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Completes a production order: consumes the components, receives the finished
 * goods at (material + overhead) / produced quantity, and books the difference.
 *
 *   Dr Persediaan (barang jadi)   Cr Persediaan (bahan baku)
 *                                 Cr Overhead Dibebankan
 */
class ManufacturingPostingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalService $journals,
        private readonly AccountMap $accounts,
    ) {}

    public function release(ProductionOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new RuntimeException('Hanya perintah produksi draft yang bisa dirilis.');
        }

        $order->forceFill(['status' => 'released'])->save();
        $order->recordActivity('released', "Perintah produksi {$order->order_no} dirilis");
    }

    /**
     * @param  float|null  $producedQty  defaults to the planned quantity
     */
    public function complete(ProductionOrder $order, ?float $producedQty = null, float $overheadCost = 0): void
    {
        if (! $order->canComplete()) {
            throw new RuntimeException('Perintah produksi ini tidak dalam status yang bisa diselesaikan.');
        }

        DB::transaction(function () use ($order, $producedQty, $overheadCost) {
            $order->load('items.product');
            $date = now()->toDateString();
            $produced = $producedQty ?? (float) $order->quantity;

            if ($produced <= 0) {
                throw new RuntimeException('Jumlah hasil produksi harus lebih dari nol.');
            }

            $materialCost = 0.0;

            foreach ($order->items as $item) {
                $consumed = (float) $item->consumed_qty ?: (float) $item->planned_qty;

                if ($consumed <= 0) {
                    continue;
                }

                $movement = $this->inventory->issue(
                    $item->product_id,
                    $order->warehouse_id,
                    $consumed,
                    'production_out',
                    $order->id,
                    $order->order_no,
                    $date,
                    'Pemakaian bahan '.$order->order_no,
                );

                $unitCost = $movement ? (float) $movement->unit_cost : (float) $item->unit_cost;
                $materialCost += $consumed * $unitCost;

                $item->forceFill(['consumed_qty' => $consumed, 'unit_cost' => $unitCost])->save();
            }

            $materialCost = round($materialCost, 2);
            $overheadCost = round($overheadCost, 2);
            $totalCost = round($materialCost + $overheadCost, 2);

            $this->inventory->receive(
                $order->product_id,
                $order->warehouse_id,
                $produced,
                $produced > 0 ? round($totalCost / $produced, 4) : 0,
                'production_in',
                $order->id,
                $order->order_no,
                $date,
                'Hasil produksi '.$order->order_no,
            );

            if ($totalCost > 0) {
                $this->journals->post(
                    [
                        ['account_id' => $this->accounts->id('acc_inventory'), 'debit' => $totalCost, 'description' => 'Barang jadi '.$order->order_no],
                        ['account_id' => $this->accounts->id('acc_inventory'), 'credit' => $materialCost, 'description' => 'Bahan baku '.$order->order_no],
                        ['account_id' => $this->accounts->id('acc_overhead_applied'), 'credit' => $overheadCost],
                    ],
                    $date,
                    'inventory',
                    'Penyelesaian produksi '.$order->order_no,
                    $order,
                    $order->order_no,
                );
            }

            $order->forceFill([
                'produced_qty' => $produced,
                'material_cost' => $materialCost,
                'overhead_cost' => $overheadCost,
                'total_cost' => $totalCost,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            $order->recordActivity('completed', "Produksi {$order->order_no} selesai");
        });
    }

    public function cancel(ProductionOrder $order): void
    {
        if ($order->status === 'cancelled') {
            throw new RuntimeException('Perintah produksi sudah dibatalkan.');
        }

        DB::transaction(function () use ($order) {
            if ($order->status === 'completed') {
                $date = now()->toDateString();
                $this->inventory->reverseDocument('production_in', $order->id, $date);
                $this->inventory->reverseDocument('production_out', $order->id, $date);
                $this->journals->reverseForSource($order);
            }

            $order->forceFill(['status' => 'cancelled'])->save();
            $order->recordActivity('cancelled', "Produksi {$order->order_no} dibatalkan");
        });
    }
}
