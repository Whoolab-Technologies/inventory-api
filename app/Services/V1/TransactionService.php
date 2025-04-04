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
use App\Models\V1\StockTransferFile;
use App\Models\V1\MaterialRequestStockTransfer;
use App\Services\Helpers;
use App\Models\V1\StockTransaction;
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
            if (empty($request->items)) {
                throw new \Exception('Invalid items data');
            } else {
                $request->items = json_decode($request->items);
            }
            foreach ($request->items as $item) {
                if (!isset($item->received_quantity)) {
                    throw new \Exception('Missing quantity');
                }
            }

            $stockTransfer = StockTransfer::findOrFail($id);
            $stockTransfer->status = $request->status;
            $stockTransfer->remarks = $request->note;
            $stockTransfer->save();
            $materialRequestStockTransfer = MaterialRequestStockTransfer::where("stock_transfer_id", $stockTransfer->id)->first();
            if (!empty($request->note)) {
                $stockTransferNote = new StockTransferNote();
                $stockTransferNote->stock_transfer_id = $materialRequestStockTransfer->stock_transfer_id;
                $stockTransferNote->material_request_id = $materialRequestStockTransfer->material_request_id;
                $stockTransferNote->notes = $request->note;
                $stockTransferNote->save();
            }
            if (!empty($request->images) && is_array($request->images)) {
                foreach ($request->images as $image) {
                    $stockTransferFile = new StockTransferFile();
                    $mimeType = $image->getMimeType();
                    $imagePath = Helpers::uploadFile($image, "images/stock-transfer/$id");

                    $stockTransferFile->file = $imagePath;
                    $stockTransferFile->file_mime_type = $mimeType;
                    $stockTransferFile->stock_transfer_id = $id;
                    $stockTransferFile->material_request_id = $materialRequestStockTransfer->material_request_id;
                    $stockTransferFile->transaction_type = "receive";
                    $stockTransferFile->save();
                }

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
                $productId = $item->product_id;
                $newReceivedQuantity = $item->received_quantity;

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

                if ($previousRemainingQuantity > 0 && $previousReceivedQuantity > 0) {
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
                StockTransferItem::where('id', $item->id)->update([
                    'product_id' => $productId,
                    'requested_quantity' => $item->requested_quantity,
                    'issued_quantity' => $item->issued_quantity,
                    'received_quantity' => $newReceivedQuantity
                ]);

                // Revert previous stock transactions
                StockTransaction::where('store_id', $fromStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('stock_movement', 'IN-TRANSIT')
                    ->delete();

                StockTransaction::where('store_id', $fromStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('quantity', $previousReceivedQuantity)
                    ->where('stock_movement', 'DECREASED')
                    ->delete();

                StockTransaction::where('store_id', $toStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('quantity', $previousReceivedQuantity)
                    ->where('stock_movement', 'INCREASED')
                    ->delete();

                // Log the new transactions
                if ($newReceivedQuantity > 0) {
                    StockTransaction::create([
                        'store_id' => $fromStoreId,
                        'product_id' => $productId,
                        'engineer_id' => $engineerId,
                        'quantity' => $newReceivedQuantity,
                        'stock_movement' => 'DECREASED',
                    ]);

                    StockTransaction::create([
                        'store_id' => $toStoreId,
                        'product_id' => $productId,
                        'engineer_id' => $engineerId,
                        'quantity' => $newReceivedQuantity,
                        'stock_movement' => 'INCREASED',
                    ]);
                }
            }

            \DB::commit();
            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function createInventoryDispatch(Request $request, $storekeeper)
    {
        \DB::beginTransaction();
        try {
            // Validate request
            if (empty($request->items) || !is_array($request->items)) {
                throw new \Exception('Invalid items data');
            }

            $items = collect($request->items);

            // Validate items structure
            foreach ($items as $item) {
                if (!isset($item['product_id'], $item['quantity'])) {
                    throw new \Exception('Missing product_id or quantity');
                }
            }

            // Fetch stock levels in one query
            $stockLevels = EngineerStock::where('engineer_id', $request->engineer_id)
                ->where('store_id', $storekeeper->store_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->get()
                ->keyBy('product_id');
            $storeStockLevels = Stock::where('store_id', $storekeeper->store_id)
                ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            // Check stock before proceeding
            foreach ($items as $item) {
                $stock = $stockLevels[$item['product_id']] ?? null;
                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product ID: {$item['product_name']}");
                }
            }
            // Create Inventory Dispatch
            $inventoryDispatch = InventoryDispatch::create([
                'dispatch_number' => 'DISPATCH-' . str_pad(InventoryDispatch::max('id') + 1001, 6, '0', STR_PAD_LEFT),
                'store_id' => $storekeeper->store_id,
                'engineer_id' => $request->engineer_id,
                'self' => $request->self,
                'representative' => $request->representative,
                "picked_at" => now()->toDateTimeString(),
            ]);

            // Create Inventory Dispatch Items and Deduct Stock
            $dispatchItems = [];
            foreach ($items as $item) {
                $dispatchItems[] = [
                    'inventory_dispatch_id' => $inventoryDispatch->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Reduce stock quantity
                $stockLevels[$item['product_id']]->decrement('quantity', $item['quantity']);
                $storeStockLevels[$item['product_id']]->decrement('quantity', $item['quantity']);

                $stockTransactions[] = [
                    'store_id' => $storekeeper->store_id,
                    'product_id' => $item['product_id'],
                    'engineer_id' => $request->engineer_id,
                    'quantity' => abs($item['quantity']),
                    'stock_movement' => "DECREASED",
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Bulk insert inventory dispatch items and stock transactions
            InventoryDispatchItem::insert($dispatchItems);
            StockTransaction::insert($stockTransactions);

            \DB::commit();
            return $inventoryDispatch->load(['items', 'store', 'engineer']);
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}