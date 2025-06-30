<?php
namespace App\Services\V1;

use App\Data\StockTransactionData;
use App\Enums\RequestType;
use App\Enums\StatusEnum;
use App\Enums\StockMovement;
use App\Enums\StockMovementType;
use App\Enums\TransactionType;
use App\Enums\TransferPartyRole;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\Stock;
use App\Models\V1\StockTransaction;
use App\Models\V1\StockTransfer;
use App\Models\V1\Store;
use App\Data\StockTransferData;
use App\Data\StockInTransitData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MaterialReturnService
{
    protected $stockTransferService;
    public function __construct(
        StockTransferService $stockTransferService,
    ) {
        $this->stockTransferService = $stockTransferService;
    }

    public function createMaterialReturns(Request $request)
    {
        \DB::beginTransaction();
        try {

            $engineerId = $request->engineer_id ?? null;
            $dnNumber = $request->dn_number ?? null;
            $products = $request->products ?? [];
            if (empty($engineerId)) {
                throw ValidationException::withMessages([
                    "engineer_id" => "Engineer ID is required."
                ]);
            }
            if (empty($dnNumber)) {
                throw ValidationException::withMessages([
                    "dn_number" => "DN Number is required."
                ]);
            }
            if (empty($products) || !is_array($products)) {
                throw ValidationException::withMessages([
                    "products" => "Products are required and must be an array."
                ]);
            }
            foreach ($products as $index => $product) {
                if (
                    !isset($product['product_id']) ||
                    !isset($product['issued']) ||
                    !is_numeric($product['issued']) ||
                    $product['issued'] <= 0
                ) {
                    throw ValidationException::withMessages([
                        "products.$index" => "Each product must have a valid product_id and issued quantity greater than 0."
                    ]);
                }
            }


            $materialReturn = new MaterialReturn();
            $materialReturn->return_number = 'IR-' . str_pad(MaterialReturn::max('id') + 1001, 6, '0', STR_PAD_LEFT);
            $materialReturn->from_store_id = $request->from_store_id;
            $materialReturn->to_store_id = $request->to_store_id;
            $materialReturn->dn_number = $dnNumber;
            $materialReturn->save();


            $materialReturnDetail = new MaterialReturnDetail();
            $materialReturnDetail->material_return_id = $materialReturn->id;
            $materialReturnDetail->engineer_id = $engineerId;
            $materialReturnDetail->save();


            foreach ($products as $product) {
                $materialReturnItem = new MaterialReturnItem();
                $materialReturnItem->material_return_id = $materialReturn->id;
                $materialReturnItem->material_return_detail_id = $materialReturnDetail->id;
                $materialReturnItem->product_id = $product['product_id'];
                $materialReturnItem->issued = $product['issued'];
                $materialReturnItem->save();
            }
            $this->createStockTransferWithItems(
                $request->from_store_id,
                $request->to_store_id,
                $materialReturn->id,
                $products,
                $dnNumber,
                $engineerId
            );

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


    private function createStockTransferWithItems(
        $fromStoreId,
        $toStoreId,
        $requestId,
        array $items,
        $dnNumber,
        $engneerId
    ) {
        $stockTransferData = new StockTransferData(
            $fromStoreId,
            $toStoreId,
            StatusEnum::IN_TRANSIT,
            $dnNumber,
            null,
            $requestId,
            RequestType::SS_RETURN,
            TransactionType::SS_CS,
            auth()->user()->id,
            TransferPartyRole::SITE_STORE,

        );
        $transfer = $this->stockTransferService->createStockTransfer($stockTransferData);

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $issued = $item['issued'];
            $transferItem = $this->stockTransferService->createStockTransferItem(
                $transfer->id,
                $productId,
                $issued,
                $issued
            );
            $this->stockTransferService->updateStock($fromStoreId, $productId, -abs($issued), $engneerId);

            $materialReturnItem = MaterialReturnItem::where('material_return_id', $requestId)
                ->where('product_id', $productId)
                ->firstOrFail();
            $stockInTransitData = new StockInTransitData(
                stockTransferId: $transfer->id,
                stockTransferItemId: $transferItem->id,
                productId: $productId,
                issuedQuantity: $issued,
                materialRequestId: null,
                materialRequestItemId: null,
                materialReturnId: $requestId,
                materialReturnItemId: $materialReturnItem->id,

            );
            \Log::info("StockInTransitData", ['data' => $stockInTransitData]);
            $this->stockTransferService->createStockInTransit($stockInTransitData);


            $stockTransactionData = new StockTransactionData(
                $fromStoreId,
                $productId,
                $engneerId,
                $issued,
                StockMovementType::SS_RETURN,
                StockMovement::TRANSIT,
                null,
                $dnNumber,
            );
            $this->stockTransferService->createStockTransaction($stockTransactionData);

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

            $engineerId = $request->engineer_id;
            $products = $request->items;
            $productIds = collect($products)->pluck('product_id')->unique();
            $stockTransfer = StockTransfer::where('request_id', $materialReturn->id)
                ->where('request_type', RequestType::SS_RETURN->value)->firstOrFail();
            $stockInTransitRecords = StockInTransit::whereIn('product_id', $productIds)
                ->whereIn('material_return_item_id', collect($products)->pluck('id'))
                ->get()
                ->keyBy('product_id');

            $user = Auth::user();
            $tokenName = optional($user?->currentAccessToken())->name;
            foreach ($products as $item) {
                $productId = $item['product_id'];
                $receivedQuantity = $item['received'];

                foreach ($stockTransfer->items as $transferItem) {
                    if ($transferItem->product_id == $productId) {
                        $transferItem->received_quantity = $receivedQuantity;
                        $transferItem->save();
                        break;
                    }
                }
                $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                if (!$stockInTransit) {
                    continue;
                }
                $remaining = max(0, $stockInTransit->issued_quantity - $receivedQuantity);
                // Update stock in transit
                $stockInTransit->update([
                    'received_quantity' => $receivedQuantity,
                    'status_id' => $remaining > 0 ? StatusEnum::PARTIALLY_RECEIVED->value : StatusEnum::RECEIVED->value,
                ]);
                // Update material return item
                MaterialReturnItem::where('id', $item['id'])->update([
                    'product_id' => $productId,
                    'received' => $receivedQuantity,
                ]);
                // Update to store stock
                $this->stockTransferService->updateStock($toStoreId, $productId, $receivedQuantity);
                // Restore remaining quantity to engineer stock
                if ($remaining > 0) {
                    $isPartiallyReceived = true;
                    $this->stockTransferService->updateStock($fromStoreId, $productId, $remaining, $engineerId);
                }

                // Delete previous transit transactions
                StockTransaction::where('store_id', $fromStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('stock_movement', StockMovement::TRANSIT)
                    ->where('type', StockMovementType::SS_RETURN)
                    ->delete();
                // Log new stock transactions
                if ($receivedQuantity > 0) {
                    StockTransaction::insert([
                        [
                            'store_id' => $fromStoreId,
                            'product_id' => $productId,
                            'engineer_id' => $engineerId,
                            'quantity' => $receivedQuantity,
                            'stock_movement' => StockMovement::OUT,
                            'type' => StockMovementType::SS_RETURN,
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
                            'stock_movement' => StockMovement::IN,
                            'type' => StockMovementType::SS_RETURN,
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

            $stockTransfer->status_id = StatusEnum::COMPLETED->value;
            $stockTransfer->received_by = $user->id;
            $stockTransfer->receiver_role = TransferPartyRole::CENTRAL_STORE;
            $stockTransfer->save();

            $materialReturn->status_id = $isPartiallyReceived
                ? StatusEnum::PARTIALLY_RECEIVED->value
                : StatusEnum::RECEIVED->value;
            $materialReturn->save();
            \DB::commit();
            return $materialReturn;

        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }


    protected function appendEngineerId($attribute, $storeId, $engineerId)
    {
        if (optional(Store::find($storeId))->is_central_store === false) {
            $attribute['engineer_id'] = $engineerId;
        }
        return $attribute;
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