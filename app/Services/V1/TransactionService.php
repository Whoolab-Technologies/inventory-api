<?php

namespace App\Services\V1;

use App\Models\V1\InventoryDispatch;
use App\Models\V1\InventoryDispatchItem;
use App\Models\V1\StockInTransit;
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

    private function updateStock(Request $request, StockTransfer $stockTransfer)
    {
        \DB::beginTransaction();
        try {
            $fromStoreId = $stockTransfer->from_store_id;
            $toStoreId = $stockTransfer->to_store_id;

            $materialRequest = $stockTransfer->materialRequestStockTransfer->materialRequest;
            $engineerId = $materialRequest->engineer_id;

            // Fetch all stock in transit records at once
            $stockInTransitRecords = StockInTransit::where('stock_transfer_id', $stockTransfer->id)
                ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            // Fetch all existing stock data for From Store, To Store, and Engineer
            $fromStoreStocks = Stock::where('store_id', $fromStoreId)
                ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            $toStoreStocks = Stock::where('store_id', $toStoreId)
                ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
                ->where('store_id', $toStoreId)
                ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            foreach ($request->items as $item) {
                $productId = $item['product_id'];
                $newReceivedQuantity = $item['received_quantity'];

                // Get the stock in transit record
                $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                if (!$stockInTransit) {
                    continue; // Skip if stock in transit not found
                }

                // Get the previously received quantity
                $previousReceivedQuantity = $stockInTransit->received_quantity;
                $previousRemainingQuantity = max(0, $stockInTransit->issued_quantity - $previousReceivedQuantity);

                // Restore previous stock values
                if ($previousReceivedQuantity > 0) {
                    if (isset($toStoreStocks[$productId])) {
                        $toStoreStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
                    }

                    if (isset($engineerStocks[$productId])) {
                        $engineerStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
                    }
                }

                if ($previousRemainingQuantity > 0) {
                    if (isset($fromStoreStocks[$productId])) {
                        $fromStoreStocks[$productId]->decrement('quantity', $previousRemainingQuantity);
                    }
                }

                // Compute new remaining quantity
                $newRemainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);

                // Update stock in transit
                $stockInTransit->received_quantity = $newReceivedQuantity;
                $stockInTransit->status = $newRemainingQuantity > 0 ? "partial_received" : "received";
                $stockInTransit->save();

                // Update from store stock if needed
                if ($newRemainingQuantity > 0) {
                    if (isset($fromStoreStocks[$productId])) {
                        $fromStoreStocks[$productId]->increment('quantity', $newRemainingQuantity);
                    }
                }

                // Update to store stock
                $toStock = $toStoreStocks[$productId] ?? new Stock([
                    'store_id' => $toStoreId,
                    'product_id' => $productId,
                    'quantity' => 0
                ]);
                $toStock->quantity += $newReceivedQuantity;
                $toStock->save();

                // Update engineer stock
                $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
                    'engineer_id' => $engineerId,
                    'store_id' => $toStoreId,
                    'product_id' => $productId,
                    'quantity' => 0
                ]);
                $engineerStock->quantity += $newReceivedQuantity;
                $engineerStock->save();

                // Update transfer item details
                StockTransferItem::where('id', $item['id'])->update([
                    'product_id' => $productId,
                    'requested_quantity' => $item['requested_quantity'],
                    'issued_quantity' => $item['issued_quantity'],
                    'received_quantity' => $newReceivedQuantity
                ]);
            }

            \DB::commit();
            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }


    public function createInventoryDispatch(Request $request, )
    {
        \DB::beginTransaction();
        try {

            if (empty($request->items) || !is_array($request->items)) {
                throw new \Exception('Invalid items data');
            }
            foreach ($request->items as $item) {
                if (!isset($item['quantity'])) {
                    throw new \Exception('Missing quantity');
                }
            }

            $inventoryDispatch = new InventoryDispatch();
            $inventoryDispatch->engineer_id = $request->engineer_id;
            $inventoryDispatch->self = $request->self;
            $inventoryDispatch->representative = $request->representative;
            $inventoryDispatch->save();
            foreach ($request->items as $item) {
                $inventoryDispatchItem = new InventoryDispatchItem();
                $inventoryDispatchItem->inventory_dispatch_id = $inventoryDispatch->id;
                $inventoryDispatchItem->product_id = $item['product_id'];
                $inventoryDispatchItem->quantity = $item['quantity'];
                $inventoryDispatchItem->save();
            }
            \DB::commit();
            return $inventoryDispatch->load('items');
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}