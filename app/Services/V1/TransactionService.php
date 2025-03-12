<?php

namespace App\Services\V1;

use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransferNote;
use App\Models\V1\Stock;
use App\Models\V1\EngineerStock;
use App\Models\V1\MaterialRequestStockTransfer;
use Illuminate\Http\Request;

class TransactionService
{

    public function updateTransaction(Request $request, int $id)
    {
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
                    throw new \Exception('Missing quantity');
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

            $this->updateStock($request, $stockTransfer);

            \DB::commit();
            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private function updateStock(Request $request, StockTransfer $stockTransfer, )
    {
        \DB::beginTransaction();
        try {
            $fromStoreId = $stockTransfer->from_store_id;
            $toStoreId = $stockTransfer->to_store_id;

            $materialRequest = $stockTransfer->materialRequestStockTransfer->materialRequest;
            $engineerId = $materialRequest->engineer_id;

            foreach ($request->items as $item) {
                $transferItem = StockTransferItem::findOrFail($item['id']);

                $fromStock = Stock::where('store_id', $fromStoreId)
                    ->where('product_id', $item['product_id'])
                    ->first();
                if ($fromStock) {
                    $fromStock->quantity -= $item['received_quantity'];
                    if ($fromStock->quantity < 0) {
                        throw new \Exception('Insufficient stock');
                    }
                    $fromStock->save();
                }

                $toStock = Stock::firstOrNew(
                    ['store_id' => $toStoreId, 'product_id' => $item['product_id']]
                );
                $toStock->quantity = ($toStock->exists ? $toStock->quantity : 0) + $item['received_quantity'];
                $toStock->save();

                $engineerStock = EngineerStock::firstOrNew(
                    ['engineer_id' => $engineerId, 'store_id' => $toStoreId, 'product_id' => $item['product_id']]
                );
                $engineerStock->quantity = ($engineerStock->exists ? $engineerStock->quantity : 0) + $item['received_quantity'];
                $engineerStock->save();

                $transferItem->product_id = $item['product_id'];
                $transferItem->requested_quantity = $item['requested_quantity'];
                $transferItem->issued_quantity = $item['issued_quantity'];
                $transferItem->received_quantity = $item['received_quantity'];
                $transferItem->save();
            }
            \DB::commit();
            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}