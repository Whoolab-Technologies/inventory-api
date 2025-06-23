<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\StockTransaction;
use App\Models\V1\Stock;
use App\Services\Helpers;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseRequestController extends Controller
{
    public function update(Request $request, $id)
    {
        \DB::beginTransaction();
        try {
            $data = $request->validate([
                'lpo' => 'nullable|string',
                'do' => 'nullable|string',
                'status_id' => 'required|integer',
                'items' => 'required|array',
                'items.*.id' => 'required|integer|exists:purchase_request_items,id',
                'items.*.received_quantity' => 'required|numeric|min:0',
            ]);

            $purchaseRequest = PurchaseRequest::with(['items', 'materialRequest'])->findOrFail($id);

            if ($data['status_id'] == 5 && empty($data['lpo'])) {
                return Helpers::sendResponse(422, null, 'LPO is required when status is Processing');
            }

            $hasOverReceived = collect($data['items'])->contains(function ($item) use ($purchaseRequest) {
                $existingItem = $purchaseRequest->items->firstWhere('id', $item['id']);
                return $existingItem->received_quantity + $item['received_quantity'] > $existingItem->quantity;
            });

            if ($hasOverReceived) {
                return Helpers::sendResponse(422, null, 'Received quantity exceeds ordered quantity for one or more items.');
            }

            $anyQuantityEntered = collect($data['items'])->contains(fn($item) => $item['received_quantity'] > 0);

            if ($anyQuantityEntered && empty($data['do'])) {
                return Helpers::sendResponse(422, null, 'Delivery Order (DO) is required when item quantity is provided.');
            }

            $purchaseRequest->fill([
                'lpo' => $data['lpo'],
                'do' => $data['do'],
                'status_id' => $data['status_id'],
            ])->save();

            foreach ($data['items'] as $itemData) {
                PurchaseRequestItem::where('id', $itemData['id'])
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->increment('received_quantity', $itemData['received_quantity']);
            }

            $purchaseRequest->refresh();

            $allFullyReceived = $purchaseRequest->items->every(fn($item) => $item->quantity == $item->received_quantity);

            if (!empty($purchaseRequest->lpo) && $purchaseRequest->status_id == 2) {
                $purchaseRequest->update(['status_id' => 5]);
            }

            if ($allFullyReceived && !empty($purchaseRequest->do)) {
                $purchaseRequest->update(['status_id' => 7]);
            }

            if (!empty($data['do'])) {
                $this->processStockMovement($purchaseRequest, $data['items']);
            }

            if ($purchaseRequest->materialRequest) {
                $purchaseRequest->materialRequest->status_id = $purchaseRequest->status_id;
                $purchaseRequest->materialRequest->save();
            }
            \DB::commit();

            $purchaseRequest->load(['materialRequest', 'items', 'items.product', 'status']);

            return Helpers::sendResponse(200, $purchaseRequest, 'Purchase Request updated successfully');

        } catch (ModelNotFoundException $e) {
            \DB::rollBack();
            return Helpers::sendResponse(404, [], 'Purchase request not found');
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::info($e->getMessage());
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

    protected function processStockMovement($purchaseRequest, $items)
    {
        $user = auth()->user();
        $centralStoreId = $user->store->id;
        $siteStoreId = $purchaseRequest->materialRequest->store_id;

        // Stock IN to Central Store
        $inTransfer = StockTransfer::create([
            'transaction_number' => 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT),
            'to_store_id' => $centralStoreId,
            'request_id' => $purchaseRequest->material_request_id,
            'send_by' => $user->id,
            'type' => 'PR',
            'transaction_type' => 'PR',
            'dn_number' => $purchaseRequest->do,
        ]);

        $this->handleStockItems($items, $purchaseRequest, $inTransfer, $centralStoreId, 'IN', true);

        // Stock OUT from Central Store to Site
        $outTransfer = StockTransfer::create([
            'transaction_number' => 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT),
            'to_store_id' => $siteStoreId,
            'from_store_id' => $centralStoreId,
            'request_id' => $purchaseRequest->material_request_id,
            'send_by' => $user->id,
            'type' => 'PR',
            'dn_number' => $purchaseRequest->do,
        ]);

        $this->handleStockItems($items, $purchaseRequest, $outTransfer, $centralStoreId, 'TRANSIT', false);

    }

    protected function handleStockItems($items, $purchaseRequest, $stockTransfer, $storeId, $movement, $isStockIn)
    {
        foreach ($items as $item) {
            $existingItem = $purchaseRequest->items->firstWhere('id', $item['id']);
            $receivedQuantity = $item['received_quantity'];
            $productId = $existingItem->product_id;

            $stockTransferItem = StockTransferItem::create([
                'stock_transfer_id' => $stockTransfer->id,
                'product_id' => $productId,
                'requested_quantity' => $existingItem->quantity,
                'issued_quantity' => $receivedQuantity,
            ]);

            StockTransaction::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'engineer_id' => $purchaseRequest->materialRequest->engineer_id,
                'quantity' => abs($receivedQuantity),
                'stock_movement' => $movement,
                'type' => 'PR',
                'dn_number' => $purchaseRequest->do ?? null,
                'lpo' => $purchaseRequest->lpo ?? null,
            ]);
            if (!$isStockIn) {
                $stockInTransit = new StockInTransit();
                $stockInTransit->stock_transfer_id = $stockTransfer->id;
                $stockInTransit->stock_transfer_item_id = $stockTransferItem->id;
                $stockInTransit->material_request_id = $purchaseRequest->material_request_id;
                $stockInTransit->material_request_item_id = $existingItem->material_request_item_id;
                $stockInTransit->product_id = $productId;
                $stockInTransit->issued_quantity = $receivedQuantity;
                $stockInTransit->save();
            }

            $stock = Stock::firstOrNew(['store_id' => $storeId, 'product_id' => $productId]);
            $stock->quantity += $isStockIn ? $receivedQuantity : -$receivedQuantity;
            $stock->save();
        }
    }


}
