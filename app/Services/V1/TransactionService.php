<?php

namespace App\Services\V1;

use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransferNote;
use App\Models\V1\MaterialRequestStockTransfer;
use Illuminate\Http\Request;

class TransactionService
{

    public function updateTransaction(Request $request, int $id)
    {
        \Log::info("updateTransaction " . json_encode($request->status));

        \DB::beginTransaction();
        try {
            if (empty($request->status)) {
                throw new \Exception('Invalid status value');
            }
            if (empty($request->items) || !is_array($request->items)) {
                throw new \Exception('Invalid items data');
            }
            foreach ($request->items as $item) {
                if (!isset($item['received_quantity'])) {
                    throw new \Exception('Invalid item data');
                }
            }

            $stockTransfer = StockTransfer::findOrFail($id);
            $stockTransfer->status = $request->status;
            $stockTransfer->remarks = $request->note;
            $stockTransfer->save();

            if (!empty($request->note)) {
                $materialRequestStockTransfer = MaterialRequestStockTransfer::findOrFail($stockTransfer->id);
                $stockTransferNote = new StockTransferNote();
                $stockTransferNote->stock_transfer_id = $materialRequestStockTransfer->stock_transfer_id;
                $stockTransferNote->material_request_id = $materialRequestStockTransfer->material_request_id;
                $stockTransferNote->notes = $request->note;
                $stockTransferNote->save();
            }

            foreach ($request->items as $item) {
                $transferItem = StockTransferItem::findOrFail($item['id']);
                $transferItem->product_id = $item['product_id'];
                $transferItem->requested_quantity = $item['requested_quantity'];
                $transferItem->issued_quantity = $item['issued_quantity'];
                $transferItem->received_quantity = $item['received_quantity'];
                $transferItem->save();
            }
            \DB::commit();
            return $stockTransfer;
        } catch (\Throwable $e) {

            \Log::info($e->getMessage());
            \DB::rollBack();
            throw $e;
        }
    }
}