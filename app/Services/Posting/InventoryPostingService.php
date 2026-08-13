<?php

namespace App\Services\Posting;

use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockTransfer;
use App\Services\AccountMap;
use App\Services\InventoryService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts warehouse transfers and stock adjustments.
 *
 * A transfer moves value between warehouses but not between accounts, so it
 * writes movements only. An adjustment changes total inventory value and is
 * booked against the inventory gain/loss accounts.
 */
class InventoryPostingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalService $journals,
        private readonly AccountMap $accounts,
    ) {}

    public function postTransfer(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'draft') {
            throw new RuntimeException("Transfer {$transfer->transfer_no} sudah diposting.");
        }

        if ($transfer->from_warehouse_id === $transfer->to_warehouse_id) {
            throw new RuntimeException('Gudang asal dan tujuan tidak boleh sama.');
        }

        DB::transaction(function () use ($transfer) {
            $transfer->load('items');
            $date = $transfer->date->toDateString();

            foreach ($transfer->items as $item) {
                $out = $this->inventory->issue(
                    $item->product_id,
                    $transfer->from_warehouse_id,
                    (float) $item->quantity,
                    'transfer_out',
                    $transfer->id,
                    $transfer->transfer_no,
                    $date,
                    'Transfer ke '.$transfer->toWarehouse->name,
                );

                // Carry the source warehouse's cost across so total value is unchanged.
                $this->inventory->receive(
                    $item->product_id,
                    $transfer->to_warehouse_id,
                    (float) $item->quantity,
                    $out ? (float) $out->unit_cost : $this->inventory->currentCost($item->product_id, $transfer->from_warehouse_id),
                    'transfer_in',
                    $transfer->id,
                    $transfer->transfer_no,
                    $date,
                    'Transfer dari '.$transfer->fromWarehouse->name,
                );
            }

            $transfer->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $transfer->recordActivity('posted', "Transfer {$transfer->transfer_no} diposting");
        });
    }

    public function cancelTransfer(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'posted') {
            throw new RuntimeException('Hanya transfer posted yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($transfer) {
            $date = now()->toDateString();
            $this->inventory->reverseDocument('transfer_in', $transfer->id, $date);
            $this->inventory->reverseDocument('transfer_out', $transfer->id, $date);

            $transfer->forceFill(['status' => 'cancelled'])->save();
            $transfer->recordActivity('cancelled', "Transfer {$transfer->transfer_no} dibatalkan");
        });
    }

    public function postAdjustment(StockAdjustment $adjustment): void
    {
        if ($adjustment->status !== 'draft') {
            throw new RuntimeException("Penyesuaian {$adjustment->adjustment_no} sudah diposting.");
        }

        DB::transaction(function () use ($adjustment) {
            $adjustment->load('items.product');
            $date = $adjustment->date->toDateString();
            $netValue = 0.0;

            foreach ($adjustment->items as $item) {
                $difference = round((float) $item->actual_qty - (float) $item->system_qty, 4);

                if ($difference === 0.0) {
                    continue;
                }

                $unitCost = (float) $item->unit_cost ?: $this->inventory->currentCost($item->product_id, $adjustment->warehouse_id);

                if ($difference > 0) {
                    $this->inventory->receive(
                        $item->product_id, $adjustment->warehouse_id, $difference, $unitCost,
                        'adjustment', $adjustment->id, $adjustment->adjustment_no, $date, $adjustment->reason
                    );
                } else {
                    $this->inventory->issue(
                        $item->product_id, $adjustment->warehouse_id, abs($difference),
                        'adjustment', $adjustment->id, $adjustment->adjustment_no, $date, $adjustment->reason, $unitCost
                    );
                }

                $item->forceFill(['difference' => $difference, 'unit_cost' => $unitCost])->save();
                $netValue += $difference * $unitCost;
            }

            $netValue = round($netValue, 2);

            if ($netValue > 0) {
                $this->journals->post(
                    [
                        ['account_id' => $this->accounts->id('acc_inventory'), 'debit' => $netValue],
                        ['account_id' => $this->accounts->id('acc_inventory_gain'), 'credit' => $netValue],
                    ],
                    $date, 'inventory', 'Selisih lebih persediaan '.$adjustment->adjustment_no,
                    $adjustment, $adjustment->adjustment_no,
                );
            } elseif ($netValue < 0) {
                $this->journals->post(
                    [
                        ['account_id' => $this->accounts->id('acc_inventory_loss'), 'debit' => abs($netValue)],
                        ['account_id' => $this->accounts->id('acc_inventory'), 'credit' => abs($netValue)],
                    ],
                    $date, 'inventory', 'Selisih kurang persediaan '.$adjustment->adjustment_no,
                    $adjustment, $adjustment->adjustment_no,
                );
            }

            $adjustment->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            $adjustment->recordActivity('posted', "Penyesuaian {$adjustment->adjustment_no} diposting");
        });
    }

    public function cancelAdjustment(StockAdjustment $adjustment): void
    {
        if ($adjustment->status !== 'posted') {
            throw new RuntimeException('Hanya penyesuaian posted yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($adjustment) {
            $this->inventory->reverseDocument('adjustment', $adjustment->id, now()->toDateString());
            $this->journals->reverseForSource($adjustment);

            $adjustment->forceFill(['status' => 'cancelled'])->save();
            $adjustment->recordActivity('cancelled', "Penyesuaian {$adjustment->adjustment_no} dibatalkan");
        });
    }
}
