<?php

namespace App\Services\V1;

use App\Data\StockTransactionData;
use App\Data\StockTransferData;
use App\Data\PurchaseRequestData;
use App\Data\StockInTransitData;
use App\Enums\StockMovementType;
use App\Enums\TransactionType;
use App\Models\V1\InventoryDispatch;
use App\Models\V1\InventoryDispatchFile;
use App\Models\V1\InventoryDispatchItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransferNote;
use App\Models\V1\Stock;
use App\Models\V1\Store;
use App\Models\V1\MaterialRequest;
use App\Models\V1\StockTransferFile;
use App\Services\Helpers;
use App\Models\V1\StockTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\StatusEnum;
use App\Enums\StockMovement;

class TransactionService
{

    protected $stockTransferService;
    protected $purchaseRequestService;

    public function __construct(
        StockTransferService $stockTransferService,
        PurchaseRequestService $purchaseRequestService,
    ) {
        $this->stockTransferService = $stockTransferService;
        $this->purchaseRequestService = $purchaseRequestService;
    }
    public function createTransaction(Request $request)
    {
        \DB::beginTransaction();
        try {
            $centralStore = Store::where('type', 'central')->firstOrFail();
            $materialRequest = MaterialRequest::findOrFail($request->id);

            $fromStoreId = $request->store_id;
            $toStoreId = $materialRequest->store_id;

            $totalRequested = 0;
            $totalIssued = 0;
            $missingItems = [];
            $isSiteToSite = $fromStoreId != $centralStore->id && $toStoreId != $centralStore->id;

            foreach ($request->items as $item) {
                $requestedQty = (int) $item['requested_quantity'];
                $issuedQty = (int) $item['issued_quantity'];
                $productId = (int) $item['product_id'];

                $totalRequested += $requestedQty;
                $totalIssued += $issuedQty;

                $missingQty = $requestedQty - $issuedQty;
                if ($missingQty > 0) {
                    $missingItems[] = [
                        'product_id' => $productId,
                        'missing_quantity' => $missingQty
                    ];
                }

                foreach ($item['engineers'] as $engineer) {
                    $engineerId = $engineer['id'];
                    $issuedQtyForEngineer = (int) $engineer['issued_qty'];

                    if ($issuedQtyForEngineer <= 0) {
                        continue;
                    }

                    $this->decrementStockWithValidation($productId, $engineerId, $issuedQtyForEngineer);

                    if ($isSiteToSite) {
                        // Step 1: Material Return from Engineer to Central Store
                        $this->createStockTransaction($fromStoreId, $productId, $engineerId, $issuedQtyForEngineer, StockMovement::OUT, StockMovementType::SS_RETURN, $request->dn_number);
                        $this->stockTransferService->updateStock($centralStore->id, $productId, $issuedQtyForEngineer, 0);
                        $this->createStockTransaction($centralStore->id, $productId, $engineerId, $issuedQtyForEngineer, StockMovement::IN, StockMovementType::SS_RETURN, $request->dn_number);

                        $this->createStockTransferAndItem($fromStoreId, $centralStore->id, $materialRequest->id, StockMovementType::SS_RETURN, TransactionType::SS_ENGG, $productId, $requestedQty, $issuedQtyForEngineer, $request->dn_number);
                    } else {
                        // Central Store to Receiving Store
                        $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQtyForEngineer, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
                        $this->createStockTransferAndItem($fromStoreId, $toStoreId, $materialRequest->id, StockMovementType::MR, TransactionType::CS_SS, $productId, $requestedQty, $issuedQtyForEngineer, $request->dn_number, true);
                    }
                }

                if ($isSiteToSite) {
                    // Step 2: Transfer from Central Store to Receiving Site
                    $centralStock = $this->stockTransferService->getOrCreateStock($centralStore->id, $productId, 0);

                    if ($issuedQty > $centralStock->quantity) {
                        throw ValidationException::withMessages([
                            "central_stock" => "Issued quantity {$issuedQty} exceeds available stock ({$centralStock->quantity}) in central store."
                        ]);
                    }

                    $centralStock->decrement('quantity', $issuedQty);

                    $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQty, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
                    $this->createStockTransferAndItem($centralStore->id, $toStoreId, $materialRequest->id, StockMovementType::MR, TransactionType::CS_SS, $productId, $requestedQty, $issuedQty, $request->dn_number, true);
                }
            }

            $materialRequest->status_id = ($totalIssued == $totalRequested) ? StatusEnum::IN_TRANSIT : StatusEnum::PARTIALLY_RECEIVED;
            $materialRequest->save();

            if (sizeof($missingItems)) {
                $purchaseRequestData = new PurchaseRequestData(
                    $materialRequest->id,
                    $materialRequest->request_number,
                    items: $missingItems
                );
                $this->purchaseRequestService->createPurchaseRequest($purchaseRequestData);
            }

            \DB::commit();
            return $materialRequest;

        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private function decrementStockWithValidation($productId, $engineerId, $quantity)
    {
        $stock = $this->stockTransferService->getStock($productId, $engineerId);
        $availableStock = $stock?->quantity ?? 0;

        if ($quantity > $availableStock) {
            throw ValidationException::withMessages([
                "engineers" => "Issued quantity {$quantity} exceeds available stock ({$availableStock}) for engineer ID {$engineerId}."
            ]);
        }

        $stock->decrement('quantity', $quantity);
    }

    private function createStockTransaction($fromStoreId, $productId, $engineerId, $quantity, $movement, $movementType, $dnNumber)
    {
        $data = new StockTransactionData(
            $fromStoreId,
            $productId,
            $engineerId,
            $quantity,
            $movement,
            $movementType,
            null,
            $dnNumber
        );

        $this->stockTransferService->createStockTransaction($data);
    }

    private function createStockTransferAndItem($fromStoreId, $toStoreId, $requestId, $requestType, $transactionType, $productId, $requestedQty, $issuedQty, $dnNumber, $createStockInTransit = false)
    {
        $transferData = new StockTransferData(
            fromStoreId: $fromStoreId,
            toStoreId: $toStoreId,
            requestId: $requestId,
            requestType: $requestType,
            transactionType: $transactionType,
            sendBy: auth()->user()->id,
            dnNumber: $dnNumber
        );

        $transfer = $this->stockTransferService->createStockTransfer($transferData);
        $transferItem = $this->stockTransferService->createStockTransferItem($transfer->id, $productId, $requestedQty, $issuedQty);
        if ($createStockInTransit) {
            $this->stockTransferService->createStockInTransit(new StockInTransitData(
                stockTransferId: $transfer->id,
                materialRequestId: $requestId,
                stockTransferItemId: $transferItem->id,
                productId: $productId,
                issuedQuantity: $issuedQty
            ));
        }
    }


    // public function createTransaction(Request $request)
    // {
    //     \DB::beginTransaction();
    //     try {
    //         // Fetch Central Store details
    //         $centralStore = Store::where('type', 'central')->firstOrFail();

    //         // Find the material request
    //         $materialRequest = MaterialRequest::findOrFail($request->id);

    //         $fromStoreId = $request->store_id;
    //         $toStoreId = $materialRequest->store_id;

    //         $totalRequested = 0;
    //         $totalIssued = 0;
    //         $missingItems = [];
    //         $isSiteToSite = $fromStoreId != $centralStore->id && $toStoreId != $centralStore->id;

    //         foreach ($request->items as $item) {
    //             $requestedQty = (int) $item['requested_quantity'];
    //             $issuedQty = (int) $item['issued_quantity'];
    //             $productId = (int) $item['product_id'];
    //             \Log::info("********************");
    //             \Log::info("     productId =>    $productId  ");
    //             $totalRequested += $requestedQty;
    //             $totalIssued += $issuedQty;
    //             $missingQty = $requestedQty - $issuedQty;
    //             if ($missingQty > 0) {
    //                 $missingItems[] = [
    //                     'product_id' => $productId,
    //                     'missing_quantity' => $missingQty
    //                 ];
    //             }
    //             // Step 1: Handle engineer-level stock
    //             foreach ($item['engineers'] as $engineer) {
    //                 $engineerId = $engineer['id'];
    //                 \Log::info("    engineerId =>    $engineerId  ");
    //                 $issuedQtyForEngineer = (int) $engineer['issued_qty'];

    //                 if ($issuedQtyForEngineer <= 0) {
    //                     continue;
    //                 }

    //                 // Get stock for engineer
    //                 $stock = $this->stockTransferService->getStock($productId, $engineerId);
    //                 $availableStock = $stock?->quantity ?? 0;

    //                 if ($issuedQtyForEngineer > $availableStock) {
    //                     throw ValidationException::withMessages([
    //                         "engineers" => " 1. Issued quantity {$issuedQtyForEngineer} exceeds available stock ({$availableStock}) for engineer ID {$engineerId}."
    //                     ]);
    //                 }

    //                 if ($isSiteToSite) {
    //                     // Step 1.1: Material Return from Engineer to Central Store
    //                     \Log::info(" Material Return from Engineer to Central Store  Start");

    //                     $stock->decrement('quantity', $issuedQtyForEngineer);

    //                     $this->stockTransferService->createStockTransaction(new StockTransactionData(
    //                         $fromStoreId,
    //                         $productId,
    //                         $engineerId,
    //                         $issuedQtyForEngineer,
    //                         StockMovement::OUT,
    //                         StockMovementType::SS_RETURN,
    //                         null,
    //                         $request->dn_number
    //                     ));

    //                     // Increment stock in Central Store
    //                     $centralStock = $this->stockTransferService->updateStock($centralStore->id, $productId, $issuedQtyForEngineer, 0);

    //                     $this->stockTransferService->createStockTransaction(new StockTransactionData(
    //                         $centralStore->id,
    //                         $productId,
    //                         $engineerId,
    //                         $issuedQtyForEngineer,
    //                         StockMovement::IN,
    //                         StockMovementType::SS_RETURN,
    //                         null,
    //                         $request->dn_number
    //                     ));

    //                     // Optional: Stock Transfer record for traceability
    //                     $transferData = new StockTransferData(
    //                         fromStoreId: $fromStoreId,
    //                         toStoreId: $centralStore->id,
    //                         requestId: $materialRequest->id,
    //                         requestType: StockMovementType::SS_RETURN,
    //                         transactionType: TransactionType::SS_ENGG,
    //                         sendBy: auth()->user()->id,
    //                         dnNumber: $request->dn_number
    //                     );
    //                     $transfer = $this->stockTransferService->createStockTransfer($transferData);
    //                     $this->stockTransferService->createStockTransferItem($transfer->id, $productId, $requestedQty, $issuedQtyForEngineer);
    //                     \Log::info(" Material Return from Engineer to Central Store End ");
    //                 } else {
    //                     \Log::info("Central Store to Receiving Store (Standard case) start");

    //                     // Central Store to Receiving Store (Standard case)
    //                     $stock->decrement('quantity', $issuedQtyForEngineer);

    //                     $this->stockTransferService->createStockTransaction(new StockTransactionData(
    //                         $centralStore->id,
    //                         $productId,
    //                         $materialRequest->engineer_id,
    //                         $issuedQtyForEngineer,
    //                         StockMovement::TRANSIT,
    //                         StockMovementType::MR,
    //                         null,
    //                         $request->dn_number
    //                     ));
    //                     \Log::info(" TransactionType::CS_SS " . TransactionType::CS_SS->value);
    //                     // Stock Transfer record
    //                     $transferData = new StockTransferData(
    //                         fromStoreId: $fromStoreId,
    //                         toStoreId: $toStoreId,
    //                         requestId: $materialRequest->id,
    //                         requestType: StockMovementType::MR,
    //                         transactionType: TransactionType::CS_SS,
    //                         sendBy: auth()->user()->id,
    //                         dnNumber: $request->dn_number
    //                     );
    //                     $transfer = $this->stockTransferService->createStockTransfer($transferData);
    //                     $this->stockTransferService->createStockTransferItem($transfer->id, $productId, $requestedQty, $issuedQtyForEngineer);
    //                     \Log::info("Central Store to Receiving Store (Standard case). End");
    //                 }
    //             }

    //             if ($isSiteToSite) {
    //                 //  Transfer from Central Store to Receiving Site
    //                 \Log::info("Transfer from Central Store to Receiving Site  after Material Return start");

    //                 // Get updated Central Stock
    //                 $centralStock = $this->stockTransferService->getOrCreateStock($centralStore->id, $productId, 0);

    //                 if ($issuedQty > $centralStock->quantity) {
    //                     throw ValidationException::withMessages([
    //                         "central_stock" => "2. Issued quantity {$issuedQty} exceeds available stock ({$centralStock->quantity}) in central store."
    //                     ]);
    //                 }

    //                 $centralStock->decrement('quantity', $issuedQty);

    //                 $this->stockTransferService->createStockTransaction(new StockTransactionData(
    //                     $centralStore->id,
    //                     $productId,
    //                     $materialRequest->engineer_id,
    //                     $issuedQty,
    //                     StockMovement::TRANSIT,
    //                     StockMovementType::MR,
    //                     null,
    //                     $request->dn_number
    //                 ));

    //                 $transferData = new StockTransferData(
    //                     fromStoreId: $centralStore->id,
    //                     toStoreId: $toStoreId,
    //                     requestId: $materialRequest->id,
    //                     requestType: StockMovementType::MR,
    //                     transactionType: TransactionType::CS_SS,
    //                     sendBy: auth()->user()->id,
    //                     dnNumber: $request->dn_number
    //                 );
    //                 $transfer = $this->stockTransferService->createStockTransfer($transferData);
    //                 $this->stockTransferService->createStockTransferItem($transfer->id, $productId, $requestedQty, $issuedQtyForEngineer);
    //                 \Log::info("Transfer from Central Store to Receiving Site  after Material Return End");
    //             }
    //         }

    //         // Material Request Status Update
    //         $materialRequest->status_id = ($totalIssued == $totalRequested)
    //             ? StatusEnum::IN_TRANSIT
    //             : StatusEnum::PARTIALLY_RECEIVED;
    //         $materialRequest->save();
    //         \Log::info("missingItems =< " . sizeof($missingItems));

    //         if (sizeof($missingItems)) {
    //             $purchaseRequestData = new PurchaseRequestData(
    //                 $materialRequest->id,
    //                 $materialRequest->request_number,
    //                 items: $missingItems
    //             );
    //             $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($purchaseRequestData);
    //             \Log::info(json_encode($purchaseRequest));
    //         }

    //         \DB::commit();
    //         return $materialRequest;

    //     } catch (\Throwable $e) {
    //         \DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function updateTransaction(Request $request, int $id)
    {
        \DB::beginTransaction();
        try {
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
            $stockTransfer->status_id = 7;
            $stockTransfer->remarks = $request->note;
            $stockTransfer->save();
            if (!empty($request->note)) {
                $stockTransferNote = new StockTransferNote();
                $stockTransferNote->stock_transfer_id = $stockTransfer->id;
                $stockTransferNote->material_request_id = $stockTransfer->request_id;
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
                    $stockTransferFile->material_request_id = $stockTransfer->request_id;
                    $stockTransferFile->transaction_type = "receive";
                    $stockTransferFile->save();
                }

            }

            $this->updateStock($request, $stockTransfer);

            $isPartiallyReceived = false;
            foreach ($stockTransfer->items as $item) {
                if ($item->received_quantity = 0 || $item->received_quantity < $item->issued_quantity) {
                    $isPartiallyReceived = true;
                    break;
                }
            }
            \Log::info('$isPartiallyReceived ' . $isPartiallyReceived);
            $materialRequest = $stockTransfer->materialRequest;
            $materialRequest->status_id = $isPartiallyReceived ? 8 : 7;
            $materialRequest->save();
            $stockTransfer->save();

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
            $dnNumber = $stockTransfer->dn_number;

            $materialRequest = $stockTransfer->materialRequest;
            $engineerId = $materialRequest->engineer_id;

            $productIds = collect($request->items)->pluck('product_id');

            // Fetch related records once
            $stockInTransitRecords = StockInTransit::where('stock_transfer_id', $stockTransfer->id)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            $toStoreQuery = Stock::where('store_id', $toStoreId)
                ->whereIn('product_id', $productIds);
            $toStore = Store::find($toStoreId);
            if ($toStore && !$toStore->is_central_store) {
                $toStoreQuery->where('engineer_id', $engineerId);
            }
            $toStoreStocks = $toStoreQuery->get()
                ->keyBy('product_id');



            // $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
            //     ->where('store_id', $toStoreId)
            //     ->whereIn('product_id', $productIds)
            //     ->get()
            //     ->keyBy('product_id');

            foreach ($request->items as $item) {
                $productId = $item->product_id;
                $newReceivedQuantity = $item->received_quantity;

                $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                if (!$stockInTransit)
                    continue;

                // Skip if already received
                if ($stockInTransit->received_quantity > 0)
                    continue;

                // Update stock in transit
                $stockInTransit->received_quantity = $newReceivedQuantity;
                $stockInTransit->status_id = $newReceivedQuantity < $stockInTransit->issued_quantity ? 8 : 11;
                $stockInTransit->save();

                // Return remaining quantity to fromStore
                $remainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);
                if ($remainingQuantity > 0) {

                    $fromStock = $fromStoreStocks[$productId] ?? new Stock([
                        'store_id' => $fromStoreId,
                        'product_id' => $productId,
                        'quantity' => 0
                    ]);
                    $fromStock->quantity += $remainingQuantity;
                    $fromStock->save();
                }
                $attributes = [
                    'store_id' => $toStoreId,
                    'product_id' => $productId,
                    'quantity' => 0
                ];

                $isCentralStore = (Store::find(id: $toStoreId))->is_central_store;
                $attributes = $this->appendEngineerId($attributes, $toStoreId, $engineerId);
                // Update toStore stock
                $toStock = $toStoreStocks[$productId] ?? new Stock($attributes);
                $toStock->quantity += $newReceivedQuantity;
                if (!$isCentralStore) {
                    $toStock->engineer_id = $engineerId;
                }
                $toStock->save();

                // Update engineer stock
                // $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
                //     'engineer_id' => $engineerId,
                //     'store_id' => $toStoreId,
                //     'product_id' => $productId,
                //     'quantity' => 0
                // ]);
                // $engineerStock->quantity += $newReceivedQuantity;
                // $engineerStock->save();

                // Update transfer item
                StockTransferItem::where('id', $item->id)->update([
                    'received_quantity' => $newReceivedQuantity
                ]);


                StockTransaction::where('store_id', $fromStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('stock_movement', 'TRANSIT')
                    ->where('type', 'MR')
                    ->delete();

                StockTransaction::create([
                    'store_id' => $fromStoreId,
                    'product_id' => $productId,
                    'engineer_id' => $engineerId,
                    'quantity' => $newReceivedQuantity,
                    'stock_movement' => 'OUT',
                    'type' => 'MR',
                    'dn_number' => $dnNumber,
                ]);

                StockTransaction::create([
                    'store_id' => $toStoreId,
                    'product_id' => $productId,
                    'engineer_id' => $engineerId,
                    'quantity' => $newReceivedQuantity,
                    'stock_movement' => 'IN',
                    'type' => 'MR',
                    'dn_number' => $dnNumber,
                ]);

            }

            \DB::commit();
            return $stockTransfer;
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

    // private function updateStock(Request $request, StockTransfer $stockTransfer)
    // {
    //     \DB::beginTransaction();
    //     try {
    //         $fromStoreId = $stockTransfer->from_store_id;
    //         $toStoreId = $stockTransfer->to_store_id;
    //         $dnNumber = $stockTransfer->dn_number;

    //         $materialRequest = $stockTransfer->materialRequestStockTransfer->materialRequest;
    //         $engineerId = $materialRequest->engineer_id;

    //         // Fetch all stock in transit records at once
    //         $stockInTransitRecords = StockInTransit::where('stock_transfer_id', $stockTransfer->id)
    //             ->whereIn('product_id', collect($request->items)->pluck('product_id'))
    //             ->get()
    //             ->keyBy('product_id');

    //         // Fetch all existing stock data for From Store, To Store, and Engineer
    //         $fromStoreStocks = Stock::where('store_id', $fromStoreId)
    //             ->whereIn('product_id', collect($request->items)->pluck('product_id'))
    //             ->get()
    //             ->keyBy('product_id');

    //         $toStoreStocks = Stock::where('store_id', $toStoreId)
    //             ->whereIn('product_id', collect($request->items)->pluck('product_id'))
    //             ->get()
    //             ->keyBy('product_id');

    //         $engineerStocks = EngineerStock::where('engineer_id', $engineerId)
    //             ->where('store_id', $toStoreId)
    //             ->whereIn('product_id', collect($request->items)->pluck('product_id'))
    //             ->get()
    //             ->keyBy('product_id');

    //         foreach ($request->items as $item) {
    //             $productId = $item->product_id;
    //             $newReceivedQuantity = $item->received_quantity;

    //             // Get the stock in transit record
    //             $stockInTransit = $stockInTransitRecords[$productId] ?? null;
    //             if (!$stockInTransit) {
    //                 continue; // Skip if stock in transit not found
    //             }

    //             // Get the previously received quantity
    //             $previousReceivedQuantity = $stockInTransit->received_quantity;
    //             $previousRemainingQuantity = max(0, $stockInTransit->issued_quantity - $previousReceivedQuantity);
    //             // Restore previous stock values
    //             if ($previousReceivedQuantity > 0) {
    //                 if (isset($toStoreStocks[$productId])) {
    //                     $toStoreStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
    //                 }

    //                 if (isset($engineerStocks[$productId])) {
    //                     $engineerStocks[$productId]->decrement('quantity', $previousReceivedQuantity);
    //                 }
    //             }

    //             if ($previousRemainingQuantity > 0 && $previousReceivedQuantity > 0) {
    //                 if (isset($fromStoreStocks[$productId])) {
    //                     $fromStoreStocks[$productId]->decrement('quantity', $previousRemainingQuantity);
    //                 }
    //             }
    //             // Compute new remaining quantity
    //             $newRemainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);

    //             // Update stock in transit
    //             $stockInTransit->received_quantity = $newReceivedQuantity;
    //             $stockInTransit->status = $newRemainingQuantity > 0 ? "partial_received" : "received";
    //             $stockInTransit->save();

    //             // Update from store stock if needed
    //             if ($newRemainingQuantity > 0) {
    //                 if (isset($fromStoreStocks[$productId])) {
    //                     $fromStoreStocks[$productId]->increment('quantity', $newRemainingQuantity);
    //                 }
    //             }

    //             // Update to store stock
    //             $toStock = $toStoreStocks[$productId] ?? new Stock([
    //                 'store_id' => $toStoreId,
    //                 'product_id' => $productId,
    //                 'quantity' => 0
    //             ]);
    //             $toStock->quantity += $newReceivedQuantity;
    //             $toStock->save();

    //             // Update engineer stock
    //             $engineerStock = $engineerStocks[$productId] ?? new EngineerStock([
    //                 'engineer_id' => $engineerId,
    //                 'store_id' => $toStoreId,
    //                 'product_id' => $productId,
    //                 'quantity' => 0
    //             ]);
    //             $engineerStock->quantity += $newReceivedQuantity;
    //             $engineerStock->save();

    //             // Update transfer item details
    //             StockTransferItem::where('id', $item->id)->update([
    //                 'product_id' => $productId,
    //                 'requested_quantity' => $item->requested_quantity,
    //                 'issued_quantity' => $item->issued_quantity,
    //                 'received_quantity' => $newReceivedQuantity
    //             ]);

    //             // Revert previous stock transactions
    //             StockTransaction::where('store_id', $fromStoreId)
    //                 ->where('product_id', $productId)
    //                 ->where('engineer_id', $engineerId)
    //                 ->where('stock_movement', 'TRANSIT')
    //                 ->where('type', 'TRANSFER')
    //                 ->delete();

    //             StockTransaction::where('store_id', $fromStoreId)
    //                 ->where('product_id', $productId)
    //                 ->where('engineer_id', $engineerId)
    //                 ->where('quantity', $previousReceivedQuantity)
    //                 ->where('stock_movement', 'OUT')
    //                 ->where('type', 'TRANSFER')
    //                 ->delete();

    //             StockTransaction::where('store_id', $toStoreId)
    //                 ->where('product_id', $productId)
    //                 ->where('engineer_id', $engineerId)
    //                 ->where('quantity', $previousReceivedQuantity)
    //                 ->where('stock_movement', 'IN')
    //                 ->where('type', 'TRANSFER')
    //                 ->delete();

    //             // Log the new transactions
    //             if ($newReceivedQuantity > 0) {
    //                 StockTransaction::create([
    //                     'store_id' => $fromStoreId,
    //                     'product_id' => $productId,
    //                     'engineer_id' => $engineerId,
    //                     'quantity' => $newReceivedQuantity,
    //                     'stock_movement' => 'OUT',
    //                     'type' => 'TRANSFER',
    //                     'dn_number' => $dnNumber,
    //                 ]);

    //                 StockTransaction::create([
    //                     'store_id' => $toStoreId,
    //                     'product_id' => $productId,
    //                     'engineer_id' => $engineerId,
    //                     'quantity' => $newReceivedQuantity,
    //                     'stock_movement' => 'IN',
    //                     'type' => 'TRANSFER',
    //                     'dn_number' => $dnNumber,
    //                 ]);
    //             }
    //         }

    //         \DB::commit();
    //         return $stockTransfer;
    //     } catch (\Throwable $e) {
    //         \DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function createInventoryDispatch(Request $request, $storekeeper)
    {
        \DB::beginTransaction();
        try {

            // Validate request
            if (empty($request->items)) {
                throw new \Exception('Invalid items data');
            } else {
                $request->items = json_decode($request->items);
            }
            $items = $request->items;
            \Log::info("items " . json_encode($items));
            // Validate items structure
            foreach ($items as $item) {
                if (!isset($item->product_id, $item->quantity)) {
                    throw new \Exception('Missing product_id or quantity');
                }
            }
            $productIds = array_column($items, 'product_id');
            \Log::info("productIds " . json_encode($productIds));

            // $stockLevels = EngineerStock::where('engineer_id', $request->engineer_id)
            //     ->where('store_id', $storekeeper->store_id)
            //     ->whereIn('product_id', $productIds)
            //     ->get()
            //     ->keyBy('product_id');
            $storeStockLevels = Stock::where('store_id', $storekeeper->store_id)
                ->whereIn('product_id', $productIds)
                ->where('engineer_id', $request->engineer_id)
                ->get()
                ->keyBy('product_id');

            // Check stock before proceeding
            foreach ($items as $item) {
                $stock = $storeStockLevels[$item->product_id] ?? null;
                if (!$stock || $stock->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for product ID: {$item->product_name}");
                }
            }
            // Create Inventory Dispatch
            $inventoryDispatch = InventoryDispatch::create([
                'dispatch_number' => 'DISPATCH-' . str_pad(InventoryDispatch::max('id') + 1001, 6, '0', STR_PAD_LEFT),
                'dn_number' => $request->dnNumber,
                'store_id' => $storekeeper->store_id,
                'engineer_id' => $request->engineer_id,
                'self' => $request->self == true ? 1 : 0,
                'representative' => $request->representative,
                "picked_at" => now()->toDateTimeString(),
            ]);

            $dispatchItems = [];
            $user = Auth::user();
            $tokenName = optional($user?->currentAccessToken())->name;

            $stockTransfer = new StockTransfer();
            $stockTransfer->transaction_number = 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT);
            $stockTransfer->to_store_id = $storekeeper->store_id;
            $stockTransfer->from_store_id = $storekeeper->store_id;
            $stockTransfer->request_id = $inventoryDispatch->id;
            $stockTransfer->remarks = $request->note;
            $stockTransfer->send_by = $user->id;
            $stockTransfer->request_type = "DISPATCH";
            $stockTransfer->transaction_type = "SS-ENGG";
            $stockTransfer->dn_number = $request->dn_number;
            $stockTransfer->send_by = $storekeeper->id;
            $stockTransfer->sender_role = "SITE STORE";
            $stockTransfer->receiver_role = "ENGINEER";
            $stockTransfer->received_by = $request->engineer_id;
            $stockTransfer->dn_number = $request->dnNumber;
            $stockTransfer->save();

            foreach ($items as $item) {
                $dispatchItems[] = [
                    'inventory_dispatch_id' => $inventoryDispatch->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $storeStockLevels[$item->product_id]->decrement('quantity', $item->quantity);
                $stockTransferItems[] = [
                    'stock_transfer_id' => $stockTransfer->id,
                    'product_id' => $item->product_id,
                    'requested_quantity' => abs($item->quantity),
                    'issued_quantity' => abs($item->quantity),
                    'received_quantity' => abs($item->quantity),
                    'created_by' => $user->id ?? null,
                    "created_type" => $tokenName,
                    "updated_by" => $user->id ?? null,
                    'updated_type' => $tokenName,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $stockTransactions[] = [
                    'store_id' => $storekeeper->store_id,
                    'product_id' => $item->product_id,
                    'engineer_id' => $request->engineer_id,
                    'quantity' => abs($item->quantity),

                    'stock_movement' => "OUT",
                    'type' => "DISPATCH",
                    'dn_number' => $request->dnNumber,
                    'created_by' => $user->id ?? null,
                    "created_type" => $tokenName,
                    "updated_by" => $user->id ?? null,
                    'updated_type' => $tokenName,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $uploadedFile) {
                    $InventoryDispatchFile = new InventoryDispatchFile();
                    $mimeType = $uploadedFile->getMimeType();
                    $imagePath = Helpers::uploadFile($uploadedFile, "files/dispatch/$inventoryDispatch->id");

                    $InventoryDispatchFile->file = $imagePath;
                    $InventoryDispatchFile->file_mime_type = $mimeType;
                    $InventoryDispatchFile->inventory_dispatch_id = $inventoryDispatch->id;
                    $InventoryDispatchFile->save();
                }
            }
            InventoryDispatchItem::insert($dispatchItems);
            StockTransaction::insert($stockTransactions);
            StockTransferItem::insert($stockTransferItems);

            \DB::commit();
            return $inventoryDispatch->load(['items.product', 'store', 'engineer', 'files']);
        } catch (\Throwable $e) {
            \Log::info($e->getmessage());
            \DB::rollBack();
            throw $e;
        }
    }
}