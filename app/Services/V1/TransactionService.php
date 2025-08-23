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
                        \Log::info("Central to Site Transfer");
                        // $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQtyForEngineer, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
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
                    \Log::info("Central to Site Transfer if its site to site");
                    //  $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQty, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
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
                $stockTransfer = $this->createStockTransferWithItems(
                    $centralStore->id,
                    $toStoreId,
                    $materialRequest->id,
                    RequestType::MR,
                    TransactionType::CS_SS,
                    $centralToSiteItems,
                    $request->dn_number,
                    true
                );
                $this->storeTransferNote($request, $stockTransfer);
                $this->storeTransferImages($request, $stockTransfer, 'transfer');
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

    private function createStockTransaction($fromStoreId, $productId, $engineerId, $quantity, $movement, $type, $dnNumber, $stockInTransitId = null)
    {

        $data = new StockTransactionData(
            $fromStoreId,
            $productId,
            $engineerId,
            $quantity,
            $type,
            $movement,
            null,
            $dnNumber,
            $stockInTransitId
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

                $stockInTransit = $this->stockTransferService->createStockInTransit(new StockInTransitData(
                    stockTransferId: $transfer->id,
                    stockTransferItemId: $transferItem->id,
                    productId: $item['product_id'],
                    issuedQuantity: $item['issued_qty'],
                    materialRequestId: $requestId,
                    materialRequestItemId: $materialRequestItem->id,
                    materialReturnId: null,
                    materialReturnItemId: null
                ));
                $this->createStockTransaction(
                    $fromStoreId,
                    $item['product_id'],
                    $materialRequestItem->materialRequest->engineer_id,
                    $item['issued_qty'],
                    StockMovement::TRANSIT,
                    StockMovementType::MR,
                    $dnNumber,
                    $stockInTransit->id
                );
            }
        }
        return $transfer;
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
                StatusEnum::PARTIALLY_RECEIVED,
                $user->id,
                $role
            );
            $this->storeTransferNote($request, $stockTransfer);

            $this->storeTransferImages($request, $stockTransfer);
            $this->applyStockTransferChanges($request, $stockTransfer);

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

        if (!$materialRequest) {
            return;
        }
        // Fetch all relevant stock transfers
        $allStockTransfers = $materialRequest->stockTransfers()
            ->with('items')
            ->where('transaction_type', TransactionType::CS_SS->value)
            ->get();

        $totalIssued = $this->calculateTotalIssuedQuantities($allStockTransfers);
        $totalReceived = $this->calculateTotalReceivedQuantities($allStockTransfers);

        // Check if PR was rejected
        $purchaseRequest = $materialRequest->purchaseRequests()->latest()->first();
        if ($purchaseRequest && $purchaseRequest->status_id == StatusEnum::REJECTED->value) {
            $materialRequest->status_id = $stockTransfer->status_id;
            $materialRequest->save();
            return;
        }

        // Initialize flags
        $hasMissingIssued = false;
        $hasPartialReceived = false;

        // Evaluate each item in the material request
        foreach ($materialRequest->items as $item) {
            $productId = $item->product_id;
            $requestedQty = $item->quantity;
            $issuedQty = $totalIssued[$productId] ?? 0;
            $receivedQty = $totalReceived[$productId] ?? 0;

            // Check if not fully issued
            if ($issuedQty < $requestedQty) {
                $hasMissingIssued = true;
                break; // No need to check further
            }

            // Check if not fully received
            if ($receivedQty < $issuedQty) {
                $hasPartialReceived = true;
            }
        }

        // Set final status
        if ($hasMissingIssued) {
            $materialRequest->status_id = StatusEnum::AWAITING_PROC->value;
        } elseif ($hasPartialReceived) {
            $materialRequest->status_id = StatusEnum::PARTIALLY_RECEIVED->value;
        } else {
            $materialRequest->status_id = StatusEnum::COMPLETED->value;
        }

        $materialRequest->save();
    }


    private function calculateTotalIssuedQuantities($stockTransfers)
    {
        $issued = [];
        foreach ($stockTransfers as $transfer) {
            foreach ($transfer->items as $item) {
                $issued[$item->product_id] = ($issued[$item->product_id] ?? 0) + $item->issued_quantity;
            }
        }
        return $issued;
    }

    private function calculateTotalReceivedQuantities($stockTransfers)
    {
        $received = [];
        foreach ($stockTransfers as $transfer) {
            foreach ($transfer->items as $item) {
                $received[$item->product_id] = ($received[$item->product_id] ?? 0) + $item->received_quantity;
            }
        }
        return $received;
    }

    // private function updateMaterialRequestStatus($stockTransfer)
    // {
    //     $materialRequest = $stockTransfer->materialRequest;

    //     // Step 1: Calculate total received quantities across all transfers
    //     $allStockTransfers = $materialRequest->stockTransfers()
    //         ->with('items')
    //         ->where('transaction_type', TransactionType::CS_SS->value)
    //         ->get();

    //     $receivedQuantities = $this->calculateTotalReceivedQuantities($allStockTransfers);

    //     $hasMissing = $this->checkForMissingItems($materialRequest, $receivedQuantities);

    //     // Step 2: Check partial received in current transaction
    //     $hasPartialReceived = $this->checkPartialReceivedInCurrentTransfer($stockTransfer);

    //     // Step 3: Decide final status
    //     if ($hasMissing) {
    //         $materialRequest->status_id = StatusEnum::AWAITING_PROC->value;
    //     } elseif ($hasPartialReceived) {
    //         $materialRequest->status_id = StatusEnum::PARTIALLY_RECEIVED->value;
    //     } else {
    //         $materialRequest->status_id = StatusEnum::COMPLETED->value;
    //     }

    //     $materialRequest->save();
    // }


    private function checkPartialReceivedInCurrentTransfer($stockTransfer)
    {
        foreach ($stockTransfer->items as $transferItem) {
            $productId = $transferItem->product_id;
            $issuedQuantity = $transferItem->issued_quantity;
            $receivedQuantity = $transferItem->received_quantity;

            if ($receivedQuantity < $issuedQuantity) {
                return true;
            }
        }

        return false;
    }

    private function checkForMissingItems($materialRequest, $receivedQuantities)
    {
        foreach ($materialRequest->items as $item) {
            $productId = $item->product_id;
            $requestedQuantity = $item->quantity;
            $totalReceived = $receivedQuantities[$productId] ?? 0;

            if ($totalReceived < $requestedQuantity) {
                return true;
            }
        }

        return false;
    }

    private function storeTransferImages(Request $request, $stockTransfer, $transactionType = "receive")
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
                'transaction_type' => $transactionType,
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

    private function applyStockTransferChanges(Request $request, StockTransfer $stockTransfer)
    {

        \DB::beginTransaction();
        try {
            $fromStoreId = $stockTransfer->from_store_id;
            $toStoreId = $stockTransfer->to_store_id;
            $dnNumber = $stockTransfer->dn_number;
            $receivedCompletely = true;
            $materialRequest = $stockTransfer->materialRequest;
            $engineerId = $materialRequest->engineer_id;

            $ids = collect($request->items)->pluck('id');
            $stockInTransitRecords = StockInTransit::where('stock_transfer_id', $stockTransfer->id)
                ->whereIn('stock_transfer_item_id', $ids)
                ->get()
                ->keyBy('stock_transfer_item_id');


            foreach ($request->items as $item) {
                $productId = $item->product_id;
                $itemId = $item->id;
                $receivedQuantity = $item->received_quantity;
                $stockInTransit = $stockInTransitRecords[$itemId] ?? null;
                if (!$stockInTransit) {
                    continue;
                }
                $stockInTransit->increment('received_quantity', $receivedQuantity);
                // Update stock in transit
                // $stockInTransit->received_quantity = $newReceivedQuantity;
                $newReceivedQuantity = $stockInTransit->refresh()->received_quantity;
                $stockInTransit->status_id = $newReceivedQuantity < $stockInTransit->issued_quantity
                    ? StatusEnum::getIdByCode('PARTIALLY_RECEIVED')
                    : StatusEnum::getIdByCode('RECEIVED');
                $stockInTransit->save();

                // Return remaining quantity to fromStore if applicable
                $remainingQuantity = max(0, $stockInTransit->issued_quantity - $newReceivedQuantity);
                if ($remainingQuantity > 0) {
                    $receivedCompletely = false;
                    //  $fromStock = $this->stockTransferService->updateStock($fromStoreId, $productId, $remainingQuantity);
                }
                $toStock = $this->stockTransferService->updateStock($toStoreId, $productId, $receivedQuantity, $engineerId);
                $this->stockTransferService->updateStockTransferItem($item->id, $newReceivedQuantity);
                $this->handleStockMovement($fromStoreId, $toStoreId, $productId, $engineerId, $receivedQuantity, [StockMovementType::MR, StockMovementType::PR], $dnNumber, $stockInTransit->id);
            }
            \Log::info("stockTransfer", ['stockTransfer' => $stockTransfer]);
            if ($receivedCompletely) {
                $stockTransfer->status_id = StatusEnum::COMPLETED->value;
            }
            $stockTransfer->save();
            \DB::commit();
            // \Log::info("stockTransfer", ['stockTransfer' => $stockTransfer]);
            return $stockTransfer;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

    }
    protected function handleStockMovement($fromStoreId, $toStoreId, $productId, $engineerId, $receivedQty, $movementType, $dnNumber, $stockInTransitId)
    {

        $transitTxn = StockTransaction::where('store_id', $fromStoreId)
            ->where('product_id', $productId)
            ->where('engineer_id', $engineerId)
            ->where('stock_movement', StockMovement::TRANSIT)
            ->whereIn('type', $movementType)
            ->where('stock_in_transit_id', $stockInTransitId)
            ->first();
        if (!$transitTxn) {
            \Log::warning("Transit stock transaction not found.");
        }
        $remainingQty = $transitTxn->quantity - $receivedQty;

        if ($remainingQty > 0) {
            $transitTxn->quantity = $remainingQty;
            $transitTxn->save();
        } else {
            // If all quantity is received, then delete the transit record
            $transitTxn->delete();
        }
        if ($receivedQty > 0) {
            $this->createStockTransaction(
                $fromStoreId,
                $productId,
                $engineerId,
                $receivedQty,
                StockMovement::OUT,
                StockMovementType::MR,
                $dnNumber
            );

            // To Store - IN movement
            $this->createStockTransaction(
                $toStoreId,
                $productId,
                $engineerId,
                $receivedQty,
                StockMovement::IN,
                StockMovementType::MR,
                $dnNumber
            );
        }

        if ($remainingQty > 0) {
            \Log::warning("Stock in transit partially received. Remaining in transit: $remainingQty units for product $productId, engineer $engineerId");
        }

    }


    public function createInventoryDispatch(Request $request, $storekeeper)
    {
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
            $inventoryDispatch = InventoryDispatch::create([
                'dispatch_number' => 'DISPATCH-' . str_pad(InventoryDispatch::max('id') + 1001, 6, '0', STR_PAD_LEFT),
                'dn_number' => $request->dnNumber,
                'store_id' => $storekeeper->store_id,
                'engineer_id' => (int) $request->engineer_id,
                'self' => $request->self == 'true' ? 1 : 0,
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

            //    \DB::commit();
            return $inventoryDispatch->load(['items.product', 'store', 'engineer', 'files']);
        } catch (\Throwable $e) {

            throw $e;
        }
    }
    private function validateItemQuantities(array $items): void
    {
        $allZero = true;

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity > 0) {
                $allZero = false;
                break;
            }
        }

        if ($allZero) {
            throw ValidationException::withMessages([
                'items' => 'At least one item must have a quantity greater than 0.',
            ]);
        }
    }

    public function createManualTransaction(Request $request, $id)
    {
        $this->validateItemQuantities($request->items);
        $centralStore = Store::where('type', 'central')->firstOrFail();
        $materialRequest = MaterialRequest::findOrFail($id);
        $centralToSiteItems = [];
        $toStoreId = $materialRequest->store_id;
        $fromStoreId = $centralStore->id;
        foreach ($request->items as $item) {
            $materialRequestItemId = (int) $item['itemId'];
            $issuedQty = (int) $item['quantity'];
            $productId = (int) $item['productId'];
            $this->createStockTransaction($fromStoreId, $productId, $materialRequest->engineer_id, $issuedQty, StockMovement::IN, StockMovementType::PR, $request->dn_number);
            $centralToSiteItems[] = [
                'product_id' => $productId,
                'requested_qty' => $issuedQty,
                'issued_qty' => $issuedQty
            ];
            //   $this->createStockTransaction($centralStore->id, $productId, $materialRequest->engineer_id, $issuedQty, StockMovement::TRANSIT, StockMovementType::MR, $request->dn_number);
        }

        $this->createCentralStockTransfer($request->dn_number, $materialRequest, $centralStore->id, $centralToSiteItems);

        $stockTransfer = $this->createStockTransferWithItems(
            $centralStore->id,
            $toStoreId,
            $materialRequest->id,
            RequestType::MR,
            TransactionType::CS_SS,
            $centralToSiteItems,
            $request->dn_number,
            true
        );

        $this->storeTransferNote($request, $stockTransfer);
        $this->storeTransferFiles($request, $stockTransfer, 'transfer');
        $materialRequest->status_id = StatusEnum::IN_TRANSIT;
        $materialRequest->save();
        return $materialRequest;
    }

    private function storeTransferFiles(Request $request, $stockTransfer, $transactionType = "receive")
    {
        $files = $request->file('files')
            ?? [];

        if (empty($files) || !is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            $filePath = Helpers::uploadFile($file, "images/stock-transfer/{$stockTransfer->id}");

            StockTransferFile::create([
                'file' => $filePath,
                'file_mime_type' => $mimeType,
                'stock_transfer_id' => $stockTransfer->id,
                'material_request_id' => $stockTransfer->request_id,
                'transaction_type' => $transactionType,
            ]);
        }
    }

    public function createCentralStockTransfer($dnNumber, $materialRequest, $centralStoreId, $items)
    {
        $stockTransferData = new StockTransferData(
            null,
            $centralStoreId,
            StatusEnum::COMPLETED,
            $dnNumber,
            null,
            $materialRequest->id,
            RequestType::PR,
            TransactionType::DIRECT,
            auth()->id(),
            TransferPartyRole::CENTRAL_STORE
        );

        $transfer = $this->stockTransferService->createStockTransfer($stockTransferData);
        foreach ($items as $item) {
            $transferItem = $this->stockTransferService->createStockTransferItem(
                $transfer->id,
                $item['product_id'],
                $item['requested_qty'],
                $item['issued_qty'],
                $item['issued_qty']
            );
        }
        return $transfer;
    }
}