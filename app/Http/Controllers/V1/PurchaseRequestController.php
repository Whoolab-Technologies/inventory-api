<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransaction;
use App\Models\V1\Stock;
use App\Services\Helpers;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseRequestController extends Controller
{
    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'lpo' => 'nullable|string',
                'do' => 'nullable|string',
                'status_id' => 'required|integer',
                'items' => 'required|array',
                'items.*.id' => 'required|integer|exists:purchase_request_items,id',
                'items.*.received_quantity' => 'required|numeric|min:0',
            ]);
            $purchaseRequest = PurchaseRequest::with('items')->findOrFail($id);
            // Validate LPO requirement BEFORE saving status
            if ($data['status_id'] == 5 && empty($data['lpo'])) {
                return Helpers::sendResponse(422, null, 'LPO is required when status is Processing');
            }
            $hasOverReceived = collect($data['items'])->contains(function ($item) use ($purchaseRequest) {
                $existingItem = $purchaseRequest->items->firstWhere('id', $item['id']);
                $newTotalReceived = $existingItem->received_quantity + $item['received_quantity'];
                return $newTotalReceived > $existingItem->quantity;
            });

            if ($hasOverReceived) {
                return Helpers::sendResponse(422, null, 'Received quantity exceeds the ordered quantity for one or more items.');
            }

            $anyQuantityEntered = collect($data['items'])->contains(function ($item) {
                return $item['received_quantity'] > 0;
            });

            if ($anyQuantityEntered && empty($data['do'])) {
                return Helpers::sendResponse(422, null, 'Delivery Order (DO) is required when item quantity is provided.');
            }

            // Now safe to update PurchaseRequest
            $purchaseRequest->lpo = $data['lpo'];
            $purchaseRequest->do = $data['do'];
            $purchaseRequest->status_id = $data['status_id'];
            $purchaseRequest->save();

            // Update item quantities
            foreach ($data['items'] as $itemData) {
                \Log::info("item");
                \Log::info($itemData['received_quantity']);

                PurchaseRequestItem::where('id', $itemData['id'])
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->increment('received_quantity', $itemData['received_quantity']);
            }

            $allFullyReceived = $purchaseRequest->items->every(function ($item) {
                return $item->quantity == $item->received_quantity;
            });

            \Log::info($purchaseRequest);

            // If LPO is added & status is 2 (Pending), change to 5 (Awaiting DO)
            if (!empty($purchaseRequest->lpo) && $purchaseRequest->status_id == 2) {
                \Log::info(message: "Changing to processing");
                $purchaseRequest->status_id = 5;
                $purchaseRequest->save();
            }


            //If all items fully received, DO is entered, set status to 7 (Completed)
            if ($allFullyReceived && !empty($purchaseRequest->do)) {
                $purchaseRequest->status_id = 7;
                $purchaseRequest->save();
            }

            if (!empty($data['do'])) {
                // For stock in 
                $user = auth()->user();
                $stockTransfers = new StockTransfer();
                $stockTransfers->to_store_id = $user->store->id;
                //$stockTransfers->to_store_id = $purchaseRequest->materialRequest->store_id;
                // $stockTransfers->from_store_id 
                $stockTransfers->request_id = $purchaseRequest->material_request_id;
                $stockTransfers->send_by = $user->id;
                $stockTransfers->type = "PR";
                $stockTransfers->dn_number = $purchaseRequest->do;
                $stockTransfers->save();

                collect($data['items'])->contains(function ($item) use ($purchaseRequest, $stockTransfers, $user) {
                    $existingItem = $purchaseRequest->items->firstWhere('id', $item['id']);
                    $productId = $existingItem->product_id;
                    $storeId = $user->store->id;
                    $receivedQuantity = $item['received_quantity'];
                    $stockTransferItem = new StockTransferItem();
                    $stockTransferItem->stock_transfer_id = $stockTransfers->id;
                    $stockTransferItem->product_id = $productId;
                    $stockTransferItem->requested_quantity = $existingItem->quantity;
                    $stockTransferItem->issued_quantity = $receivedQuantity;
                    $stockTransferItem->save();
                    // stock in transaction  central store
                    $stockTransaction = new StockTransaction();
                    $stockTransaction->store_id = $storeId;
                    $stockTransaction->product_id = $productId;
                    $stockTransaction->engineer_id = $existingItem->materialRequest->engineer_id;
                    $stockTransaction->quantity = abs($receivedQuantity);
                    $stockTransaction->stock_movement = "IN";
                    $stockTransaction->type = "PR";
                    $stockTransaction->dn_number = $purchaseRequest->do ?? null;
                    $stockTransaction->save();

                    // show stock in to the central store
                    $toStoreStocks = Stock::where('store_id', $storeId)
                        ->whereIn('product_id', $productId)
                        ->get()
                        ->keyBy('product_id');
                    $toStock = $toStoreStocks[$productId] ?? new Stock([
                        'store_id' => $storeId,
                        'product_id' => $productId,
                        'quantity' => 0,
                    ]);
                    $toStock->quantity += $receivedQuantity;
                    $toStock->save();

                });

                // For stock out of central store



                $stockTransfers = new StockTransfer();
                $stockTransfers->to_store_id = $purchaseRequest->materialRequest->store_id;
                $stockTransfers->from_store_id = $user->store->id;
                $stockTransfers->request_id = $purchaseRequest->material_request_id;
                $stockTransfers->send_by = $user->id;
                $stockTransfers->type = "PR";
                $stockTransfers->dn_number = $purchaseRequest->do;
                $stockTransfers->save();

                collect($data['items'])->contains(function ($item) use ($purchaseRequest, $stockTransfers, $user) {
                    $existingItem = $purchaseRequest->items->firstWhere('id', $item['id']);
                    $productId = $existingItem->product_id;
                    $storeId = $user->store->id;
                    $receivedQuantity = $item['received_quantity'];
                    $stockTransferItem = new StockTransferItem();
                    $stockTransferItem->stock_transfer_id = $stockTransfers->id;
                    $stockTransferItem->product_id = $productId;
                    $stockTransferItem->requested_quantity = $existingItem->quantity;
                    $stockTransferItem->issued_quantity = $receivedQuantity;
                    $stockTransferItem->save();
                    // stock in transaction  central store
                    $stockTransaction = new StockTransaction();
                    $stockTransaction->store_id = $storeId;
                    $stockTransaction->product_id = $productId;
                    $stockTransaction->engineer_id = $existingItem->materialRequest->engineer_id;
                    $stockTransaction->quantity = abs($receivedQuantity);
                    $stockTransaction->stock_movement = "TRANSIT";
                    $stockTransaction->type = "PR";
                    $stockTransaction->dn_number = $purchaseRequest->do ?? null;
                    $stockTransaction->save();

                    // show stock in to the central store
                    $toStoreStocks = Stock::where('store_id', $storeId)
                        ->whereIn('product_id', $productId)
                        ->get()
                        ->keyBy('product_id');
                    $toStock = $toStoreStocks[$productId] ?? new Stock([
                        'store_id' => $storeId,
                        'product_id' => $productId,
                        'quantity' => 0,
                    ]);
                    $toStock->quantity -= $receivedQuantity;
                    $toStock->save();

                });

            }

            // Final Response with updated data
            $purchaseRequest->load(['materialRequest', 'items', 'items.product', 'status']);

            return Helpers::sendResponse(200, $purchaseRequest, 'Purchase Request updated successfully');

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Purchase request not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error updating purchase request: ' . $e->getMessage());
        }
    }
    public function index(Request $request)
    {
        try {
            $purchaseRequests = PurchaseRequest::with([
                'status',
                'materialRequest',
                'materialRequest.status',
                'items',
                'items.product',
                'transactions.items',
                'transactions.items.product'
            ])->orderBy('created_at', 'desc')->get();

            // $purchaseRequests = $purchaseRequests->map(function ($request) {
            //     return $this->processPurchaseRequestItems($request);
            // });

            return Helpers::sendResponse(200, $purchaseRequests, 'Purchase requests retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving purchase requests: ' . $e->getMessage());
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $purchaseRequest = PurchaseRequest::with([
                'status',
                'materialRequest',
                'materialRequest.status',
                'items',
                'items.product',
                'transactions.items',
                'transactions.items.product',
                'transactions.status'
            ])->findOrFail($id);

            //  $purchaseRequest = $this->processPurchaseRequestItems($purchaseRequest, false);

            return Helpers::sendResponse(200, $purchaseRequest, 'Purchase request retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, $e->getMessage());
        }
    }

    private function processPurchaseRequestItems($purchaseRequest, $unset = true)
    {
        $transactions = $purchaseRequest->transactions;

        $allTransactionItems = $transactions
            ->pluck('items')
            ->flatten(1)
            ->groupBy('product_id');

        $processedItems = collect($purchaseRequest->items)->map(function ($item) use ($allTransactionItems) {
            $stockGroup = $allTransactionItems->get($item->product_id);
            $item->received_quantity = $stockGroup ? $stockGroup->sum('received_quantity') : 0;

            return $item;
        });

        $purchaseRequest->setRelation('items', $processedItems);
        if ($unset)
            unset($purchaseRequest->transactions);

        return $purchaseRequest;
    }
}
