<?php
namespace App\Services\V1;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\EngineerStock;
use App\Models\V1\Stock;
use App\Models\V1\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $materialReturn->dn_number = $request->dn_number ?? null;
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

                    // 🔻 Decrease From Store Stock
                    $fromStock = Stock::firstOrNew([
                        'store_id' => $request->from_store_id,
                        'product_id' => $product['product_id'],
                    ]);
                    $fromStock->quantity = max(0, ($fromStock->quantity ?? 0) - $product['issued']);
                    $fromStock->save();

                    // 🔻 Decrease Engineer Stock
                    $engineerStock = EngineerStock::firstOrNew([
                        'engineer_id' => $engineer['engineer_id'],
                        'product_id' => $product['product_id'],
                    ]);
                    $engineerStock->quantity = max(0, ($engineerStock->quantity ?? 0) - $product['issued']);
                    $engineerStock->save();

                    // 📦 Log Stock Transaction
                    $stockTransaction = new StockTransaction();
                    $stockTransaction->store_id = $request->from_store_id;
                    $stockTransaction->product_id = $product['product_id'];
                    $stockTransaction->engineer_id = $engineer['engineer_id'];
                    $stockTransaction->quantity = abs($product['issued']);
                    $stockTransaction->stock_movement = "TRANSIT";
                    $stockTransaction->type = "RETURN";
                    $stockTransaction->dn_number = $request->dn_number ?? null;
                    $stockTransaction->save();
                }
            }

            \DB::commit();
            return $materialReturn->load([
                'status',
                'fromStore',
                'toStore',
                'details.engineer',
                'details.items.product',
            ]);

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
            $materialReturn = $this->updateStock($request, $materialReturn);
            \DB::commit();
            return $materialReturn->load([
                'fromStore',
                'status',
                'toStore',
                'details.engineer',
                'details.items.product',
            ]);
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
            $isPartiallyReceived = false;
            foreach ($request->details as $details) {
                $engineerId = $details['engineer_id'];
                $productIds = collect($details['items'])->pluck('product_id')->unique();

                $stockInTransitRecords = StockInTransit::whereIn('product_id', $productIds)
                    ->whereIn('material_return_item_id', collect($details['items'])->pluck('id'))
                    ->get()
                    ->keyBy('product_id');

                $toStoreStocks = Stock::where('store_id', $toStoreId)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                $fromStoreStocks = Stock::where('store_id', $fromStoreId)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
                    ->where('store_id', $fromStoreId)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');
                $user = Auth::user();
                $tokenName = optional($user?->currentAccessToken())->name;
                foreach ($details['items'] as $item) {
                    $productId = $item['product_id'];
                    $receivedQuantity = $item['received'];

                    $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                    if (!$stockInTransit)
                        continue;

                    $remaining = max(0, $stockInTransit->issued_quantity - $receivedQuantity);

                    // Update stock in transit
                    $stockInTransit->update([
                        'received_quantity' => $receivedQuantity,
                        'status' => $remaining > 0 ? 8 : 7,
                    ]);

                    // Update material return item
                    MaterialReturnItem::where('id', $item['id'])->update([
                        'product_id' => $productId,
                        'received' => $receivedQuantity,
                    ]);

                    // Update to store stock
                    $toStock = $toStoreStocks[$productId] ?? new Stock([
                        'store_id' => $toStoreId,
                        'product_id' => $productId,
                        'quantity' => 0,
                    ]);
                    $toStock->quantity += $receivedQuantity;
                    $toStock->save();

                    // Restore remaining quantity to engineer stock
                    if ($remaining > 0) {
                        $isPartiallyReceived = true;
                        $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
                            'engineer_id' => $engineerId,
                            'store_id' => $fromStoreId,
                            'product_id' => $productId,
                            'quantity' => 0,
                        ]);
                        $engineerStock->quantity += $remaining;
                        $engineerStock->save();

                        // Also restore remaining to from store stock
                        $fromStoreStock = $fromStoreStocks[$productId] ?? new Stock([
                            'store_id' => $fromStoreId,
                            'product_id' => $productId,
                            'quantity' => 0,
                        ]);
                        $fromStoreStock->quantity += $remaining;
                        $fromStoreStock->save();
                    }
                    StockTransaction::where('store_id', $fromStoreId)
                        ->where('product_id', $productId)
                        ->where('engineer_id', $engineerId)
                        ->where('stock_movement', 'TRANSIT')
                        ->where('type', 'RETURN')
                        ->delete();

                    // Log stock transactions
                    if ($receivedQuantity > 0) {
                        StockTransaction::insert([
                            [
                                'store_id' => $fromStoreId,
                                'product_id' => $productId,
                                'engineer_id' => $engineerId,
                                'quantity' => $receivedQuantity,
                                'stock_movement' => 'OUT',
                                'type' => 'RETURN',
                                'dn_number' => $materialReturn->dn_number ?? null,
                                'created_by' => $user->id ?? null,
                                "created_type" => $tokenName,
                                "updated_by" => $user->id ?? null,
                                'updated_type' => $tokenName,
                                'created_at' => now(),
                                'updated_at' => now()
                            ],
                            [
                                'store_id' => $toStoreId,
                                'product_id' => $productId,
                                'engineer_id' => $engineerId,
                                'quantity' => $receivedQuantity,
                                'stock_movement' => 'IN',
                                'type' => 'RETURN',
                                'dn_number' => $materialReturn->dn_number ?? null,
                                'created_by' => $user->id ?? null,
                                "created_type" => $tokenName,
                                "updated_by" => $user->id ?? null,
                                'updated_type' => $tokenName,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]
                        ]);
                    }
                }
            }
            $materialReturn->status_id = 11;
            if ($isPartiallyReceived) {
                $materialReturn->status_id = 9;
            }
            $materialReturn->save();
            \DB::commit();
            return $materialReturn;

        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error("Stock update failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }


    // private function updateStock(Request $request, MaterialReturn $materialReturn)
    // {
    //     \DB::beginTransaction();
    //     try {
    //         $fromStoreId = $materialReturn->from_store_id;
    //         $toStoreId = $materialReturn->to_store_id;
    //         \Log::info("Material Return Update Request", ['request' => $request->all()]);

    //         foreach ($request->details as $details) {
    //             $engineerId = $details['engineer_id'];
    //             $productIds = collect($details['items'])->pluck('product_id')->unique();

    //             // Fetch bulk data
    //             $stockInTransitRecords = StockInTransit::whereIn('product_id', $productIds)
    //                 ->whereIn('material_return_item_id', collect($details['items'])->pluck('id'))
    //                 ->get()
    //                 ->keyBy('product_id');

    //             $fromStoreStocks = Stock::where('store_id', $fromStoreId)
    //                 ->whereIn('product_id', $productIds)
    //                 ->get()
    //                 ->keyBy('product_id');

    //             $toStoreStocks = Stock::where('store_id', $toStoreId)
    //                 ->whereIn('product_id', $productIds)
    //                 ->get()
    //                 ->keyBy('product_id');

    //             $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
    //                 ->where('store_id', $fromStoreId)
    //                 ->whereIn('product_id', $productIds)
    //                 ->get()
    //                 ->keyBy('product_id');

    //             foreach ($details['items'] as $item) {
    //                 $productId = $item['product_id'];
    //                 $newReceivedQuantity = $item['received'];

    //                 $stockInTransit = $stockInTransitRecords[$productId] ?? null;
    //                 if (!$stockInTransit)
    //                     continue;

    //                 $previousReceived = $stockInTransit->received_quantity;
    //                 $previousRemaining = max(0, $stockInTransit->issued_quantity - $previousReceived);

    //                 // Restore previous stocks
    //                 if ($previousReceived > 0) {
    //                     $toStoreStocks[$productId]?->decrement('quantity', $previousReceived);
    //                     $engineerStocks[$productId]?->decrement('quantity', $previousReceived);
    //                 }
    //                 if ($previousRemaining > 0 && $previousReceived > 0) {
    //                     $fromStoreStocks[$productId]?->decrement('quantity', $previousRemaining);
    //                 }

    //                 $newRemaining = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);

    //                 // Update stock in transit
    //                 $stockInTransit->update([
    //                     'received_quantity' => $newReceivedQuantity,
    //                     'status' => $newRemaining > 0 ? 'partial_received' : 'received',
    //                 ]);

    //                 // Update From Store Stock
    //                 if ($newRemaining > 0) {
    //                     $fromStoreStocks[$productId]?->increment('quantity', $newRemaining);
    //                 }

    //                 // Update To Store Stock
    //                 $toStock = $toStoreStocks[$productId] ?? new Stock([
    //                     'store_id' => $toStoreId,
    //                     'product_id' => $productId,
    //                     'quantity' => 0
    //                 ]);
    //                 $toStock->quantity += $newReceivedQuantity;
    //                 $toStock->save();

    //                 // Update Engineer Stock
    //                 $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
    //                     'engineer_id' => $engineerId,
    //                     'store_id' => $fromStoreId,
    //                     'product_id' => $productId,
    //                     'quantity' => 0
    //                 ]);
    //                 $engineerStock->quantity -= $newReceivedQuantity;
    //                 $engineerStock->save();

    //                 // Update Return Item
    //                 MaterialReturnItem::where('id', $item['id'])->update([
    //                     'product_id' => $productId,
    //                     'received' => $newReceivedQuantity,
    //                 ]);

    //                 // Clean previous transactions
    //                 StockTransaction::whereIn('stock_movement', ['IN-TRANSIT', 'DECREASED', 'INCREASED'])
    //                     ->where('store_id', [$fromStoreId, $toStoreId])
    //                     ->where('product_id', $productId)
    //                     ->where('engineer_id', $engineerId)
    //                     ->where('quantity', $previousReceived)
    //                     ->delete();

    //                 // Create new transactions
    //                 if ($newReceivedQuantity > 0) {
    //                     StockTransaction::insert([
    //                         [
    //                             'store_id' => $fromStoreId,
    //                             'product_id' => $productId,
    //                             'engineer_id' => $engineerId,
    //                             'quantity' => $newReceivedQuantity,
    //                             'stock_movement' => 'DECREASED',
    //                         ],
    //                         [
    //                             'store_id' => $toStoreId,
    //                             'product_id' => $productId,
    //                             'engineer_id' => $engineerId,
    //                             'quantity' => $newReceivedQuantity,
    //                             'stock_movement' => 'INCREASED',
    //                         ]
    //                     ]);
    //                 }
    //             }
    //         }

    //         \DB::commit();
    //         return $materialReturn;

    //     } catch (\Throwable $e) {
    //         \DB::rollBack();
    //         \Log::error("Stock update failed", ['error' => $e->getMessage()]);
    //         throw $e;
    //     }
    // }



    // public function createMaterialReturns(Request $request)
    // {
    //     try {
    //         if (empty($request->engineers) || !is_array($request->engineers)) {
    //             throw new \InvalidArgumentException('The engineers field is required and must be an array.');
    //         }

    //         \DB::beginTransaction();
    //         $materialReturn = new MaterialReturn();
    //         $materialReturn->from_store_id = $request->from_store_id;
    //         $materialReturn->to_store_id = $request->to_store_id;
    //         $materialReturn->save();


    //         foreach ($request->engineers as $engineer) {
    //             $materialReturnDetail = new MaterialReturnDetail();
    //             $materialReturnDetail->material_return_id = $materialReturn->id;
    //             $materialReturnDetail->engineer_id = $engineer['engineer_id'];
    //             $materialReturnDetail->save();

    //             foreach ($engineer['products'] as $product) {
    //                 $materialReturnItem = new MaterialReturnItem();
    //                 $materialReturnItem->material_return_id = $materialReturn->id;
    //                 $materialReturnItem->material_return_detail_id = $materialReturnDetail->id;
    //                 $materialReturnItem->product_id = $product['product_id'];
    //                 $materialReturnItem->issued = $product['issued'];
    //                 $materialReturnItem->save();

    //                 $stockInTransit = new StockInTransit();
    //                 $stockInTransit->material_return_id = $materialReturn->id;
    //                 $stockInTransit->material_return_item_id = $materialReturnItem->id;
    //                 $stockInTransit->product_id = $product['product_id'];
    //                 $stockInTransit->issued_quantity = $product['issued'];
    //                 $stockInTransit->save();


    //                 $stockTransaction = new StockTransaction();
    //                 $stockTransaction->store_id = $request->from_store_id;
    //                 $stockTransaction->product_id = $product['product_id'];
    //                 $stockTransaction->engineer_id = $engineer['engineer_id'];
    //                 $stockTransaction->quantity = abs($product['issued']);
    //                 $stockTransaction->stock_movement = "IN-TRANSIT";
    //                 $stockTransaction->save();
    //             }
    //         }

    //         \DB::commit();
    //         return $materialReturn->load([
    //             'fromStore',
    //             'toStore',
    //             'details.engineer',
    //             'details.items.product',
    //         ]);

    //     } catch (\Throwable $th) {
    //         \DB::rollBack();
    //         throw $th;
    //     }
    // }


    // private function updateStock(Request $request, MaterialReturn $materialReturn)
    // {
    //     \DB::beginTransaction();
    //     try {
    //         $fromStoreId = $materialReturn->from_store_id;
    //         $toStoreId = $materialReturn->to_store_id;
    //         \Log::info("toStoreId " . json_encode($request->all()));

    //         $returnDetails = $request->details;

    //         foreach ($returnDetails as $details) {

    //             $engineerId = $details['engineer_id'];

    //             \Log::info("materialReturn->id " . $materialReturn->id);

    //             \Log::info("product_ids " . collect($details['items'])->pluck('product_id'));
    //             foreach ($details['items'] as $item) {
    //                 \Log::info("item " . $item['id']);

    //                 // Fetch all stock in transit records at once
    //                 $stockInTransitRecords = StockInTransit::
    //                     where('material_return_item_id', $item['id'])
    //                     ->where('product_id', $item['product_id'])
    //                     ->select('id', 'material_return_item_id', 'product_id', 'issued_quantity', 'received_quantity')
    //                     ->get()
    //                     ->keyBy('product_id');

    //                 \Log::info("********* stockInTransitRecords ******");
    //                 \Log::info(json_encode($stockInTransitRecords));

    //                 // // Fetch all existing stock data for From Store, To Store, and Engineer
    //                 $fromStoreStocks = Stock::where('store_id', $fromStoreId)
    //                     ->where('product_id', $item['product_id'])
    //                     ->get()
    //                     ->keyBy('product_id');

    //                 \Log::info("********* fromStoreStocks ******");
    //                 \Log::info(json_encode($fromStoreStocks));

    //                 $toStoreStocks = Stock::where('store_id', $toStoreId)
    //                     ->where('product_id', $item['product_id'])
    //                     ->get()
    //                     ->keyBy('product_id');
    //                 \Log::info("********* toStoreStocks ******");
    //                 \Log::info(json_encode($toStoreStocks));
    //                 $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
    //                     ->where('store_id', $fromStoreId)
    //                     ->where('product_id', $item['product_id'])
    //                     ->get()
    //                     ->keyBy('product_id');

    //                 \Log::info("********* engineerStocks ******");
    //                 \Log::info(json_encode($engineerStocks));



    //                 // foreach ($request->items as $item) {
    //                 $productId = $item['product_id'];
    //                 $newReceivedQuantity = $item['received'];

    //                 // Get the stock in transit record
    //                 $stockInTransit = $stockInTransitRecords[$productId] ?? null;
    //                 if (!$stockInTransit) {
    //                     continue; // Skip if stock in transit not found
    //                 }

    //                 // Get the previously received quantity
    //                 $previousReceivedQuantity = $stockInTransit->received_quantity;
    //                 $previousRemainingQuantity = max(0, $stockInTransit->issued_quantity - $previousReceivedQuantity);
    //                 // Restore previous stock values
    //                 if ($previousReceivedQuantity > 0) {
    //                     if (isset($toStoreStocks[$productId])) {
    //                         $toStoreStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
    //                     }

    //                     if (isset($engineerStocks[$productId])) {
    //                         $engineerStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
    //                     }
    //                 }

    //                 if ($previousRemainingQuantity > 0 && $previousReceivedQuantity > 0) {
    //                     if (isset($fromStoreStocks[$productId])) {
    //                         $fromStoreStocks[$productId]->decrement('quantity', $previousRemainingQuantity);
    //                     }
    //                 }
    //                 // Compute new remaining quantity
    //                 $newRemainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);

    //                 // Update stock in transit
    //                 $stockInTransit->received_quantity = $newReceivedQuantity;
    //                 $stockInTransit->status = $newRemainingQuantity > 0 ? "partial_received" : "received";
    //                 $stockInTransit->save();

    //                 // Update from store stock if needed
    //                 if ($newRemainingQuantity > 0) {
    //                     if (isset($fromStoreStocks[$productId])) {
    //                         $fromStoreStocks[$productId]->increment('quantity', $newRemainingQuantity);
    //                     }
    //                 }

    //                 // Update to store stock
    //                 $toStock = $toStoreStocks[$productId] ?? new Stock([
    //                     'store_id' => $toStoreId,
    //                     'product_id' => $productId,
    //                     'quantity' => 0
    //                 ]);
    //                 $toStock->quantity += $newReceivedQuantity;
    //                 $toStock->save();

    //                 // Update engineer stock
    //                 $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
    //                     'engineer_id' => $engineerId,
    //                     'store_id' => $fromStoreId,
    //                     'product_id' => $productId,
    //                     'quantity' => 0
    //                 ]);
    //                 $engineerStock->quantity -= $newReceivedQuantity;
    //                 $engineerStock->save();

    //                 // Update transfer item details
    //                 MaterialReturnItem::where('id', $item['id'])->update([
    //                     'product_id' => $productId,
    //                     'received' => $newReceivedQuantity
    //                 ]);

    //                 //Revert previous stock transactions
    //                 StockTransaction::where('store_id', $fromStoreId)
    //                     ->where('product_id', $productId)
    //                     ->where('engineer_id', $engineerId)
    //                     ->where('stock_movement', 'IN-TRANSIT')
    //                     ->delete();

    //                 StockTransaction::where('store_id', $fromStoreId)
    //                     ->where('product_id', $productId)
    //                     ->where('engineer_id', $engineerId)
    //                     ->where('quantity', $previousReceivedQuantity)
    //                     ->where('stock_movement', 'DECREASED')
    //                     ->delete();

    //                 StockTransaction::where('store_id', $toStoreId)
    //                     ->where('product_id', $productId)
    //                     ->where('engineer_id', $engineerId)
    //                     ->where('quantity', $previousReceivedQuantity)
    //                     ->where('stock_movement', 'INCREASED')
    //                     ->delete();

    //                 // Log the new transactions
    //                 if ($newReceivedQuantity > 0) {
    //                     StockTransaction::create([
    //                         'store_id' => $fromStoreId,
    //                         'product_id' => $productId,
    //                         'engineer_id' => $engineerId,
    //                         'quantity' => $newReceivedQuantity,
    //                         'stock_movement' => 'DECREASED',
    //                     ]);

    //                     StockTransaction::create([
    //                         'store_id' => $toStoreId,
    //                         'product_id' => $productId,
    //                         'engineer_id' => $engineerId,
    //                         'quantity' => $newReceivedQuantity,
    //                         'stock_movement' => 'INCREASED',
    //                     ]);
    //                     //}
    //                 }
    //             }
    //         }
    //         \DB::commit();
    //         return $materialReturn;
    //     } catch (\Throwable $e) {
    //         \DB::rollBack();
    //         throw $e;
    //     }
    // }
}