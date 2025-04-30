<?php
namespace App\Services\V1;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\EngineerStock;
use App\Models\V1\Stock;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransaction;
use Illuminate\Http\Request;

class MaterialReturnService
{
    public function createMaterialReturns(Request $request)
    {
        try {
            if (empty($request->engineers) || !is_array($request->engineers)) {
                throw new \InvalidArgumentException('The engineers field is required and must be an array.');
            }

            \DB::beginTransaction();
            $materialReturn = new MaterialReturn();
            $materialReturn->from_store_id = $request->from_store_id;
            $materialReturn->to_store_id = $request->to_store_id;
            $materialReturn->save();


            foreach ($request->engineers as $engineer) {
                $materialReturnDetail = new MaterialReturnDetail();
                $materialReturnDetail->material_return_id = $materialReturn->id;
                $materialReturnDetail->engineer_id = $engineer['engineer_id'];
                $materialReturnDetail->save();

                foreach ($engineer['products'] as $product) {
                    $materialReturnItem = new MaterialReturnItem();
                    $materialReturnItem->material_return_id = $materialReturn->id;
                    $materialReturnItem->material_return_detail_id = $materialReturnDetail->id;
                    $materialReturnItem->product_id = $product['product_id'];
                    $materialReturnItem->issued = $product['issued'];
                    $materialReturnItem->save();

                    $stockInTransit = new StockInTransit();
                    $stockInTransit->material_return_id = $materialReturn->id;
                    $stockInTransit->material_return_item_id = $materialReturnItem->id;
                    $stockInTransit->product_id = $product['product_id'];
                    $stockInTransit->issued_quantity = $product['issued'];
                    $stockInTransit->save();
                }
            }

            \DB::commit();
            return $materialReturn->load(['fromStore', 'toStore', 'details.items']);

        } catch (\Throwable $th) {
            \DB::rollBack();
            throw $th;
        }
    }

    public function updateMaterialReturns($id, $request)
    {
        \DB::beginTransaction();
        try {
            $materialReturn = MaterialReturn::where('id', $id)->first();
            $this->updateStock($request, $materialReturn);
            \DB::commit();
            return $materialReturn;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private function updateStock(Request $request, MaterialReturn $materialReturn)
    {
        \DB::beginTransaction();
        try {
            $fromStoreId = $materialReturn->from_store_id;
            $toStoreId = $materialReturn->to_store_id;
            \Log::info("toStoreId " . $toStoreId);
            \Log::info("fromStoreId " . $fromStoreId);

            $materialReturnDetails = $materialReturn->details;

            foreach ($materialReturnDetails as $materialReturnDetail) {
                $engineerId = $materialReturnDetail->engineer_id;

                \Log::info("engineerId " . $engineerId);
                \Log::info("request->items " . json_encode($request->details));

                // Fetch all stock in transit records at once
                $stockInTransitRecords = StockInTransit::where('material_return_id', $materialReturn->id)
                    ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                    ->get()
                    ->keyBy('product_id');

                \Log::info(json_encode($stockInTransitRecords));

                // // Fetch all existing stock data for From Store, To Store, and Engineer
                // $fromStoreStocks = Stock::where('store_id', $fromStoreId)
                //     ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                //     ->get()
                //     ->keyBy('product_id');

                // $toStoreStocks = Stock::where('store_id', $toStoreId)
                //     ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                //     ->get()
                //     ->keyBy('product_id');

                // $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
                //     ->where('store_id', $toStoreId)
                //     ->whereIn('product_id', collect($request->items)->pluck('product_id'))
                //     ->get()
                //     ->keyBy('product_id');

                // foreach ($request->items as $item) {
                //     $productId = $item->product_id;
                //     $newReceivedQuantity = $item->received_quantity;

                //     // Get the stock in transit record
                //     $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                //     if (!$stockInTransit) {
                //         continue; // Skip if stock in transit not found
                //     }

                //     // Get the previously received quantity
                //     $previousReceivedQuantity = $stockInTransit->received_quantity;
                //     $previousRemainingQuantity = max(0, $stockInTransit->issued_quantity - $previousReceivedQuantity);
                //     // Restore previous stock values
                //     if ($previousReceivedQuantity > 0) {
                //         if (isset($toStoreStocks[$productId])) {
                //             $toStoreStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
                //         }

                //         if (isset($engineerStocks[$productId])) {
                //             $engineerStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
                //         }
                //     }

                //     if ($previousRemainingQuantity > 0 && $previousReceivedQuantity > 0) {
                //         if (isset($fromStoreStocks[$productId])) {
                //             $fromStoreStocks[$productId]->decrement('quantity', $previousRemainingQuantity);
                //         }
                //     }
                //     // Compute new remaining quantity
                //     $newRemainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);

                //     // Update stock in transit
                //     $stockInTransit->received_quantity = $newReceivedQuantity;
                //     $stockInTransit->status = $newRemainingQuantity > 0 ? "partial_received" : "received";
                //     $stockInTransit->save();

                //     // Update from store stock if needed
                //     if ($newRemainingQuantity > 0) {
                //         if (isset($fromStoreStocks[$productId])) {
                //             $fromStoreStocks[$productId]->increment('quantity', $newRemainingQuantity);
                //         }
                //     }

                //     // Update to store stock
                //     $toStock = $toStoreStocks[$productId] ?? new Stock([
                //         'store_id' => $toStoreId,
                //         'product_id' => $productId,
                //         'quantity' => 0
                //     ]);
                //     $toStock->quantity += $newReceivedQuantity;
                //     $toStock->save();

                //     // Update engineer stock
                //     $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
                //         'engineer_id' => $engineerId,
                //         'store_id' => $toStoreId,
                //         'product_id' => $productId,
                //         'quantity' => 0
                //     ]);
                //     $engineerStock->quantity += $newReceivedQuantity;
                //     $engineerStock->save();

                //     // Update transfer item details
                //     StockTransferItem::where('id', $item->id)->update([
                //         'product_id' => $productId,
                //         'requested_quantity' => $item->requested_quantity,
                //         'issued_quantity' => $item->issued_quantity,
                //         'received_quantity' => $newReceivedQuantity
                //     ]);

                //     // Revert previous stock transactions
                //     StockTransaction::where('store_id', $fromStoreId)
                //         ->where('product_id', $productId)
                //         ->where('engineer_id', $engineerId)
                //         ->where('stock_movement', 'IN-TRANSIT')
                //         ->delete();

                //     StockTransaction::where('store_id', $fromStoreId)
                //         ->where('product_id', $productId)
                //         ->where('engineer_id', $engineerId)
                //         ->where('quantity', $previousReceivedQuantity)
                //         ->where('stock_movement', 'DECREASED')
                //         ->delete();

                //     StockTransaction::where('store_id', $toStoreId)
                //         ->where('product_id', $productId)
                //         ->where('engineer_id', $engineerId)
                //         ->where('quantity', $previousReceivedQuantity)
                //         ->where('stock_movement', 'INCREASED')
                //         ->delete();

                //     // Log the new transactions
                //     if ($newReceivedQuantity > 0) {
                //         StockTransaction::create([
                //             'store_id' => $fromStoreId,
                //             'product_id' => $productId,
                //             'engineer_id' => $engineerId,
                //             'quantity' => $newReceivedQuantity,
                //             'stock_movement' => 'DECREASED',
                //         ]);

                //         StockTransaction::create([
                //             'store_id' => $toStoreId,
                //             'product_id' => $productId,
                //             'engineer_id' => $engineerId,
                //             'quantity' => $newReceivedQuantity,
                //             'stock_movement' => 'INCREASED',
                //         ]);
                //     }
                // }
            }
            \DB::commit();
            return $materialReturn;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}