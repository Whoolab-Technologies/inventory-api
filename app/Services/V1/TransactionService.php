<?php

namespace App\Services\V1;

use App\Data\MaterialReturnData;
use App\Data\StockTransactionData;
use App\Data\StockTransferData;
use App\Data\PurchaseRequestData;
use App\Data\StockInTransitData;
use App\Enums\RequestType;
use App\Enums\StockMovementType;
use App\Enums\TransactionType;
use App\Enums\TransferPartyRole;
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
use App\Models\V1\MaterialRequestItem;
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
            $isSiteToSite = $fromStoreId != $centralStore->id && $toStoreId != $centralStore->id;

            $totalRequested = 0;
            $totalIssued = 0;
            $missingItems = [];
            $materialReturnItems = [];
            $centralToSiteItems = [];

            foreach ($request->items as $item) {
                $requestedQty = (int) $item['requested_quantity'];
                $issuedQty = (int) $item['issued_quantity'];
                $productId = (int) $item['product_id'];

                if ($issuedQty > $requestedQty) {
                    throw ValidationException::withMessages([
                        "issued_quantity" => "Issued quantity ($issuedQty) cannot be greater than requested quantity ($requestedQty) for product ID $productId."
                    ]);
                }

                $totalRequested += $requestedQty;
                $totalIssued += $issuedQty;

                $missingQty = $requestedQty - $issuedQty;
                if ($missingQty > 0) {
                    $missingItems[] = [
                        'product_id' => $productId,
                        'missing_quantity' => $missingQty
                    ];
                }

                foreach ($item['engineers'] ?? [] as $engineer) {
                    $engineerId = $engineer['id'];
                    $issuedQtyForEngineer = (int) $engineer['issued_qty'];

                    if ($issuedQtyForEngineer <= 0)
                        continue;
                    if ($issuedQtyForEngineer > $requestedQty) {
                        throw ValidationException::withMessages([
                            "engineers" => "Issued quantity ({$issuedQtyForEngineer}) for engineer ID {$engineerId} cannot be greater than requested quantity ($requestedQty) for product ID $productId."
                        ]);
                    }

                    $this->decrementStockWithValidation($productId, $engineerId, $issuedQtyForEngineer);

                    if ($isSiteToSite) {
                        // Material Return from Site to Central
                        $this->createStockTransaction($fromStoreId, $productId, $engineerId, $issuedQtyForEngineer, StockMovement::OUT, StockMovementType::SS_RETURN, $request->dn_number);
                        $this->stockTransferService->updateStock($centralStore->id, $productId, $issuedQtyForEngineer, 0);
                        $this->createStockTransaction($centralStore->id, $productId, $engineerId, $issuedQtyForEngineer, StockMovement::IN, StockMovementType::SS_RETURN, $request->dn_number);

                        $materialReturnItems[] = [
                            'engineer_id' => $engineerId,
                            'product_id' => $productId,
                            'requested_qty' => $requestedQty,
                            'issued_qty' => $issuedQtyForEngineer
                        ];
                    } else {
                        // Central to Site Transfer
                        $centralToSiteItems[] = [
                            'product_id' => $productId,
                            'requested_qty' => $requestedQty,
                            'issued_qty' => $issuedQtyForEngineer
                        ];

                        $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQtyForEngineer, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
                    }
                }

                if ($isSiteToSite) {
                    // Central to Site Transfer Preparation
                    $centralStock = $this->stockTransferService->getOrCreateStock($centralStore->id, $productId, 0);
                    if ($issuedQty > $centralStock->quantity) {
                        throw ValidationException::withMessages([
                            "central_stock" => "Issued quantity {$issuedQty} exceeds available stock ({$centralStock->quantity}) in central store."
                        ]);
                    }
                    $centralStock->decrement('quantity', $issuedQty);

                    $centralToSiteItems[] = [
                        'product_id' => $productId,
                        'requested_qty' => $requestedQty,
                        'issued_qty' => $issuedQty
                    ];
                    $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQty, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
                }
            }

            // Handle Material Return Transfer
            if ($isSiteToSite && count($materialReturnItems)) {
                $materialReturn = $this->stockTransferService->createMaterialReturnWithItems(new MaterialReturnData(
                    $fromStoreId,
                    $centralStore->id,
                    $this->groupItemsByEngineer($materialReturnItems),
                    $request->dn_number
                ));

                $this->createStockTransferWithItems(
                    $fromStoreId,
                    $centralStore->id,
                    $materialReturn->id,
                    RequestType::SS_RETURN,
                    TransactionType::SS_CS,
                    $materialReturnItems,
                    $request->dn_number,
                    false
                );
            }

            // Handle Central to Site Transfer
            if (count($centralToSiteItems)) {
                $this->createStockTransferWithItems(
                    $centralStore->id,
                    $toStoreId,
                    $materialRequest->id,
                    RequestType::MR,
                    TransactionType::CS_SS,
                    $centralToSiteItems,
                    $request->dn_number,
                    true
                );
            }

            $materialRequest->status_id = ($totalIssued == $totalRequested) ? StatusEnum::IN_TRANSIT : StatusEnum::PROCESSING;
            $materialRequest->save();

            if (count($missingItems)) {
                $this->purchaseRequestService->createPurchaseRequest(new PurchaseRequestData(
                    $materialRequest->id,
                    $materialRequest->request_number,
                    items: $missingItems
                ));
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

    private function createStockTransaction($fromStoreId, $productId, $engineerId, $quantity, $movement, $ype, $dnNumber)
    {

        $data = new StockTransactionData(
            $fromStoreId,
            $productId,
            $engineerId,
            $quantity,
            $ype,
            $movement,
            null,
            $dnNumber
        );

        $this->stockTransferService->createStockTransaction($data);
    }

    private function createStockTransferWithItems(
        $fromStoreId,
        $toStoreId,
        $requestId,
        $requestType,
        $transactionType,
        array $items,
        $dnNumber,
        $createStockInTransit = false
    ) {
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

        foreach ($items as $item) {
            $transferItem = $this->stockTransferService->createStockTransferItem(
                $transfer->id,
                $item['product_id'],
                $item['requested_qty'],
                $item['issued_qty']
            );

            if ($createStockInTransit) {
                $materialRequestItem = MaterialRequestItem::where('material_request_id', $requestId)
                    ->where('product_id', $item['product_id'])
                    ->firstOrFail();

                $this->stockTransferService->createStockInTransit(new StockInTransitData(
                    stockTransferId: $transfer->id,
                    stockTransferItemId: $transferItem->id,
                    productId: $item['product_id'],
                    issuedQuantity: $item['issued_qty'],
                    materialRequestId: $requestId,
                    materialRequestItemId: $materialRequestItem->id,
                    materialReturnId: null,
                    materialReturnItemId: null
                ));
            }
        }
    }
    private function groupItemsByEngineer(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['engineer_id']][] = [
                'product_id' => $item['product_id'],
                'requested_qty' => $item['requested_qty'],
                'issued_qty' => $item['issued_qty']
            ];
        }
        return $grouped;
    }


    // private function createStockTransferAndItem($fromStoreId, $toStoreId, $requestId, $requestType, $transactionType, $productId, $requestedQty, $issuedQty, $dnNumber, $createStockInTransit = false)
    // {
    //     $transferData = new StockTransferData(
    //         fromStoreId: $fromStoreId,
    //         toStoreId: $toStoreId,
    //         requestId: $requestId,
    //         requestType: $requestType,
    //         transactionType: $transactionType,
    //         sendBy: auth()->user()->id,
    //         dnNumber: $dnNumber
    //     );

    //     $transfer = $this->stockTransferService->createStockTransfer($transferData);
    //     $transferItem = $this->stockTransferService->createStockTransferItem(
    //         $transfer->id,
    //         $productId,
    //         $requestedQty,
    //         $issuedQty
    //     );
    //     if ($createStockInTransit) {
    //         $materialRequestItem = MaterialRequestItem::where('material_request_id', $requestId)
    //             ->firstOrFail();
    //         $this->stockTransferService->createStockInTransit(new StockInTransitData(
    //             stockTransferId: $transfer->id,
    //             materialRequestId: $requestId,
    //             materialRequestItemId: $materialRequestItem->id,
    //             stockTransferItemId: $transferItem->id,
    //             productId: $productId,
    //             issuedQuantity: $issuedQty
    //         ));
    //     }
    // }


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
            $user = auth()->user();
            $role = ($user->load('store'))->is_central_store ? TransferPartyRole::CENTRAL_STORE : TransferPartyRole::SITE_STORE;
            $stockTransfer = $this->stockTransferService->updateStockTransfer(
                $id,
                StatusEnum::COMPLETED,
                $user->id,
                $role
            );
            if (!empty($request->note)) {
                $stockTransferNote = new StockTransferNote();
                $stockTransferNote->stock_transfer_id = $stockTransfer->id;
                $stockTransferNote->material_request_id = $stockTransfer->request_id;
                $stockTransferNote->notes = $request->note;
                $stockTransferNote->save();
                \Log::info("Stock Transfer Note added", ['note' => $request->note]);
            }

            if (!empty($request->images) && is_array($request->images)) {
                foreach ($request->images as $image) {
                    $mimeType = $image->getMimeType();
                    $imagePath = Helpers::uploadFile($image, "images/stock-transfer/$id");

                    $stockTransferFile = new StockTransferFile();
                    $stockTransferFile->file = $imagePath;
                    $stockTransferFile->file_mime_type = $mimeType;
                    $stockTransferFile->stock_transfer_id = $id;
                    $stockTransferFile->material_request_id = $stockTransfer->request_id;
                    $stockTransferFile->transaction_type = "receive";
                    $stockTransferFile->save();

                }
            }
            $this->updateStock($request, $stockTransfer);

            // $isPartiallyReceived = false;
            // foreach ($stockTransfer->items as $item) {
            //     if ($item->received_quantity == 0 || $item->received_quantity < $item->issued_quantity) {
            //         $isPartiallyReceived = true;
            //         break;
            //     }
            // }
            // $materialRequest = $stockTransfer->materialRequest;
            // $materialRequest->status_id = $isPartiallyReceived ? StatusEnum::PARTIALLY_RECEIVED : StatusEnum::COMPLETED;
            // $materialRequest->save();
            $hasMissing = false;
            $hasPartialReceived = false;

            $stockTransferItems = $stockTransfer->items->keyBy('product_id');
            $materialRequest = $stockTransfer->materialRequest;
            foreach ($materialRequest->items as $item) {
                $productId = $item->product_id;
                $requestedQuantity = $item->quantity;

                $stockTransferItem = $stockTransferItems[$productId] ?? null;

                $issuedQuantity = $stockTransferItem?->issued_quantity ?? 0;
                $receivedQuantity = $stockTransferItem?->received_quantity ?? 0;

                // Product missing entirely or issued partially
                if (!$stockTransferItem || $issuedQuantity < $requestedQuantity) {
                    $hasMissing = true;
                    break;
                }

                // Product issued fully but received partially
                if ($receivedQuantity < $issuedQuantity) {
                    $hasPartialReceived = true;
                }
            }

            if ($hasMissing) {
                $materialRequest->status_id = StatusEnum::AWAITING_PROC->value;
            } elseif ($hasPartialReceived) {
                $materialRequest->status_id = StatusEnum::PARTIALLY_RECEIVED->value;
            } else {
                $materialRequest->status_id = StatusEnum::COMPLETED->value;
            }

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

            $stockInTransitRecords = StockInTransit::where('stock_transfer_id', $stockTransfer->id)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            $toStoreQuery = Stock::where('store_id', $toStoreId)
                ->whereIn('product_id', $productIds);

            $toStore = Store::find($toStoreId);
            if ($toStore && !$toStore->is_central_store) {
                $toStoreQuery->where('engineer_id', $engineerId);
            } else {
            }

            //    $toStoreStocks = $toStoreQuery->get()->keyBy('product_id');

            foreach ($request->items as $item) {
                $productId = $item->product_id;
                $newReceivedQuantity = $item->received_quantity;

                $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                if (!$stockInTransit) {
                    continue;
                }

                if ($stockInTransit->received_quantity > 0) {
                    continue;
                }

                // Update stock in transit
                $stockInTransit->received_quantity = $newReceivedQuantity;
                $stockInTransit->status_id = $newReceivedQuantity < $stockInTransit->issued_quantity
                    ? StatusEnum::getIdByCode('PARTIALLY_RECEIVED')
                    : StatusEnum::getIdByCode('RECEIVED');
                $stockInTransit->save();

                // Return remaining quantity to fromStore if applicable
                $remainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);
                if ($remainingQuantity > 0) {
                    $fromStock = $this->stockTransferService->updateStock($fromStoreId, $productId, $remainingQuantity);
                    \Log::info("Returned {$remainingQuantity} units to fromStore ID: {$fromStoreId} for product ID: {$productId}");
                }

                // Add received quantity to toStore
                $toStock = $this->stockTransferService->updateStock($toStoreId, $productId, $newReceivedQuantity);

                // Update stock transfer item
                $this->stockTransferService->updateStockTransferItem($item->id, $newReceivedQuantity);

                // Log stock movement
                $this->handleStockMovement($fromStoreId, $toStoreId, $productId, $engineerId, $newReceivedQuantity, StockMovementType::MR, $dnNumber);
            }

            \DB::commit();

            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

    }
    protected function handleStockMovement($fromStoreId, $toStoreId, $productId, $engineerId, $quantity, $movementType, $dnNumber)
    {
        StockTransaction::where('store_id', $fromStoreId)
            ->where('product_id', $productId)
            ->where('engineer_id', $engineerId)
            ->where('stock_movement', StockMovement::TRANSIT)
            ->where('type', $movementType, )
            ->delete();
        // From Store - OUT movement
        $this->createStockTransaction(
            $fromStoreId,
            $productId,
            $engineerId,
            $quantity,
            StockMovement::OUT,
            $movementType,
            $dnNumber
        );

        // To Store - IN movement
        $this->createStockTransaction(
            $toStoreId,
            $productId,
            $engineerId,
            $quantity,
            StockMovement::IN,
            $movementType,
            $dnNumber
        );
    }


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

            $transferData = new StockTransferData(
                $storekeeper->store_id,
                $storekeeper->store_id,
                StatusEnum::COMPLETED,
                $request->dnNumber,
                $request->note,
                $inventoryDispatch->id,
                RequestType::DISPATCH,
                TransactionType::SS_ENGG,
                $storekeeper->id,
                TransferPartyRole::SITE_STORE,
                $request->engineer_id,
                TransferPartyRole::ENGINEER->value,
                $request->note
            );

            $transfer = $this->stockTransferService->createStockTransfer($transferData);


            foreach ($items as $item) {
                $dispatchItems[] = [
                    'inventory_dispatch_id' => $inventoryDispatch->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $this->stockTransferService->updateStock($storekeeper->store_id, $item->product_id, -abs($item->quantity), $request->engineer_id);

                $stockTransferItems[] = [
                    'stock_transfer_id' => $transfer->id,
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
                    'stock_movement' => StockMovement::OUT,
                    'type' => StockMovementType::DISPATCH,
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