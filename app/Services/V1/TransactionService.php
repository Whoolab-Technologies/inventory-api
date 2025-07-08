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
    public function updateTransaction(Request $request, int $id)
    {
        \DB::beginTransaction();

        try {
            $items = $this->validateAndParseItems($request);
            $request->items = $items;
            $stockTransfer = StockTransfer::findOrFail($id);
            $user = auth()->user();
            $role = ($user->load('store'))->is_central_store ? TransferPartyRole::CENTRAL_STORE : TransferPartyRole::SITE_STORE;

            $stockTransfer = $this->stockTransferService->updateStockTransfer(
                $id,
                StatusEnum::COMPLETED,
                $user->id,
                $role
            );
            $this->storeTransferNote($request, $stockTransfer);

            $this->storeTransferImages($request, $stockTransfer);


            $this->updateStock($request, $stockTransfer);
            $stockTransfer->save();
            $stockTransfer->refresh();

            $this->updateMaterialRequestStatus($stockTransfer);

            \DB::commit();

            return $stockTransfer;

        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }
    private function updateMaterialRequestStatus($stockTransfer)
    {
        $materialRequest = $stockTransfer->materialRequest;

        // Step 1: Calculate total received quantities across all transfers
        $allStockTransfers = $materialRequest->stockTransfers()
            ->with('items')
            ->where('transaction_type', TransactionType::CS_SS->value)
            ->get();

        $receivedQuantities = $this->calculateTotalReceivedQuantities($allStockTransfers);

        $hasMissing = $this->checkForMissingItems($materialRequest, $receivedQuantities);

        // Step 2: Check partial received in current transaction
        $hasPartialReceived = $this->checkPartialReceivedInCurrentTransfer($stockTransfer);

        // Step 3: Decide final status
        if ($hasMissing) {
            $materialRequest->status_id = StatusEnum::AWAITING_PROC->value;
        } elseif ($hasPartialReceived) {
            $materialRequest->status_id = StatusEnum::PARTIALLY_RECEIVED->value;
        } else {
            $materialRequest->status_id = StatusEnum::COMPLETED->value;
        }

        $materialRequest->save();
    }
    private function checkPartialReceivedInCurrentTransfer($stockTransfer)
    {
        foreach ($stockTransfer->items as $transferItem) {
            $productId = $transferItem->product_id;
            $issuedQuantity = $transferItem->issued_quantity;
            $receivedQuantity = $transferItem->received_quantity;

            if ($receivedQuantity < $issuedQuantity) {
                \Log::notice("Partial received in current transfer for product_id: {$productId}");
                return true; // Partial received found in current transfer
            }
        }

        return false; // All items fully received in this transfer
    }

    private function checkForMissingItems($materialRequest, $receivedQuantities)
    {
        foreach ($materialRequest->items as $item) {
            $productId = $item->product_id;
            $requestedQuantity = $item->quantity;
            $totalReceived = $receivedQuantities[$productId] ?? 0;


            if ($totalReceived < $requestedQuantity) {
                \Log::warning("Missing or insufficient received quantity for product_id: {$productId}");
                return true; // Missing items found
            }
        }

        return false; // All items fully received
    }

    private function calculateTotalReceivedQuantities($allStockTransfers)
    {
        $receivedQuantities = [];

        foreach ($allStockTransfers as $transfer) {
            foreach ($transfer->items as $transferItem) {
                $productId = $transferItem->product_id;
                \Log::info('Transfer Item', [
                    "product_id" => $productId,
                    "received_quantity" => $transferItem->received_quantity
                ]);

                $receivedQuantities[$productId] = ($receivedQuantities[$productId] ?? 0) + $transferItem->received_quantity;
            }
        }

        \Log::info('Final Received Quantities', [
            'receivedQuantities' => $receivedQuantities
        ]);

        return $receivedQuantities;
    }

    private function storeTransferImages(Request $request, $stockTransfer)
    {
        if (empty($request->images) || !is_array($request->images)) {
            return;
        }

        foreach ($request->images as $image) {
            $mimeType = $image->getMimeType();
            $imagePath = Helpers::uploadFile($image, "images/stock-transfer/{$stockTransfer->id}");

            StockTransferFile::create([
                'file' => $imagePath,
                'file_mime_type' => $mimeType,
                'stock_transfer_id' => $stockTransfer->id,
                'material_request_id' => $stockTransfer->request_id,
                'transaction_type' => "receive",
            ]);
        }
    }

    private function storeTransferNote(Request $request, $stockTransfer)
    {
        if (empty($request->note)) {
            return;
        }

        StockTransferNote::create([
            'stock_transfer_id' => $stockTransfer->id,
            'material_request_id' => $stockTransfer->request_id,
            'notes' => $request->note,
        ]);
    }

    private function validateAndParseItems(Request $request)
    {
        if (empty($request->items)) {
            throw new \Exception('Invalid items data');
        }

        $items = json_decode($request->items);

        if (empty($items) || !is_array($items)) {
            throw new \Exception('Invalid items data');
        }

        foreach ($items as $item) {
            if (!isset($item->received_quantity)) {
                throw new \Exception('Missing quantity');
            }
        }

        return $items;
    }


    // public function updateTransaction(Request $request, int $id)
    // {
    //     \DB::beginTransaction();
    //     try {
    //         if (empty($request->items)) {
    //             throw new \Exception('Invalid items data');
    //         } else {
    //             $request->items = json_decode($request->items);
    //         }

    //         foreach ($request->items as $item) {
    //             if (!isset($item->received_quantity)) {
    //                 throw new \Exception('Missing quantity');
    //             }
    //         }

    //         $stockTransfer = StockTransfer::findOrFail($id);
    //         $user = auth()->user();
    //         $role = ($user->load('store'))->is_central_store ? TransferPartyRole::CENTRAL_STORE : TransferPartyRole::SITE_STORE;
    //         $stockTransfer = $this->stockTransferService->updateStockTransfer(
    //             $id,
    //             StatusEnum::COMPLETED,
    //             $user->id,
    //             $role
    //         );
    //         if (!empty($request->note)) {
    //             $stockTransferNote = new StockTransferNote();
    //             $stockTransferNote->stock_transfer_id = $stockTransfer->id;
    //             $stockTransferNote->material_request_id = $stockTransfer->request_id;
    //             $stockTransferNote->notes = $request->note;
    //             $stockTransferNote->save();
    //         }

    //         if (!empty($request->images) && is_array($request->images)) {
    //             foreach ($request->images as $image) {
    //                 $mimeType = $image->getMimeType();
    //                 $imagePath = Helpers::uploadFile($image, "images/stock-transfer/$id");

    //                 $stockTransferFile = new StockTransferFile();
    //                 $stockTransferFile->file = $imagePath;
    //                 $stockTransferFile->file_mime_type = $mimeType;
    //                 $stockTransferFile->stock_transfer_id = $id;
    //                 $stockTransferFile->material_request_id = $stockTransfer->request_id;
    //                 $stockTransferFile->transaction_type = "receive";
    //                 $stockTransferFile->save();

    //             }
    //         }
    //         $this->updateStock($request, $stockTransfer);
    //         $stockTransfer->save();
    //         $stockTransfer->refresh();
    //         $this->updateMaterialRequestStatus($stockTransfer);

    //         \DB::commit();

    //         return $stockTransfer;

    //     } catch (\Throwable $e) {
    //         \DB::rollBack();
    //         throw $e;
    //     }
    // }

    // private function updateMaterialRequestStatus($stockTransfer)
    // {

    //     $materialRequest = $stockTransfer->materialRequest;

    //     $allStockTransfers = $materialRequest->stockTransfers()
    //         ->with('items')
    //         ->where('transaction_type', TransactionType::CS_SS->value)
    //         ->get();

    //     $receivedQuantities = [];
    //     $transferCounts = [];

    //     foreach ($allStockTransfers as $transfer) {
    //         foreach ($transfer->items as $transferItem) {
    //             $productId = $transferItem->product_id;
    //             \Log::info('transferItem ', ["productId" => $productId, 'received_quantity', $transferItem->received_quantity]);
    //             $receivedQuantities[$productId] = ($receivedQuantities[$productId] ?? 0) + $transferItem->received_quantity;
    //             $transferCounts[$productId] = ($transferCounts[$productId] ?? 0) + 1;
    //         }
    //     }
    //     \Log::info('final receivedQuantities', [
    //         'receivedQuantities' => $receivedQuantities
    //     ]);
    //     $hasMissing = false;
    //     $hasPartialReceived = false;

    //     foreach ($materialRequest->items as $item) {
    //         $productId = $item->product_id;
    //         $requestedQuantity = $item->quantity;
    //         $totalReceived = $receivedQuantities[$productId] ?? 0;
    //         $transferCount = $transferCounts[$productId] ?? 0;

    //         \Log::info("Checking Product", [
    //             'product_id' => $productId,
    //             'requested_quantity' => $requestedQuantity,
    //             'total_received_quantity' => $totalReceived,
    //             'transfer_count' => $transferCount
    //         ]);

    //         if ($totalReceived < $requestedQuantity) {
    //             \Log::warning("Missing or insufficient received quantity for product_id: {$productId}");
    //             $hasMissing = true;
    //             break;
    //         }

    //         if ($transferCount > 1) {
    //             $hasPartialReceived = true;
    //         }
    //     }

    //     if ($hasMissing) {
    //         $materialRequest->status_id = StatusEnum::AWAITING_PROC->value;
    //     } elseif ($hasPartialReceived) {
    //         $materialRequest->status_id = StatusEnum::PARTIALLY_RECEIVED->value;
    //     } else {
    //         $materialRequest->status_id = StatusEnum::COMPLETED->value;
    //     }

    //     $materialRequest->save();
    // }

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
                }

                // Add received quantity to toStore
                $toStock = $this->stockTransferService->updateStock($toStoreId, $productId, $newReceivedQuantity, $engineerId);

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
            // Validate items structure
            foreach ($items as $item) {
                if (!isset($item->product_id, $item->quantity)) {
                    throw new \Exception('Missing product_id or quantity');
                }
            }
            $productIds = array_column($items, 'product_id');

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
                'engineer_id' => (int) $request->engineer_id,
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
            \DB::rollBack();
            throw $e;
        }
    }
}