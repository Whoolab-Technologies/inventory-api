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
use App\Services\V1\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseRequestController extends Controller
{
    protected $purchaseRequestService;

    public function __construct(PurchaseRequestService $purchaseRequestService)
    {
        $this->purchaseRequestService = $purchaseRequestService;
    }
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
            $purchaseRequests = PurchaseRequest::with($this->prRelations())
                ->orderByDesc('id')
                ->get();

            $purchaseRequests = $purchaseRequests->map(fn($pr) => $this->formatPurchaseRequest($pr));

            return Helpers::sendResponse(200, $purchaseRequests, 'Purchase requests retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving purchase requests: ' . $e->getMessage());
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $pr = PurchaseRequest::with($this->prRelations())->findOrFail($id);

            $response = $this->formatPurchaseRequest($pr);

            return Helpers::sendResponse(200, $response, 'Purchase request retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, $e->getMessage());
        }
    }


    public function createLpo(Request $request, $id)
    {
        try {
            $lpo = $this->purchaseRequestService->createLpoWithItems($request);
            return Helpers::sendResponse(200, $lpo, 'Lpo created successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, $e->getMessage());
        }
    }


    private function prRelations(): array
    {
        return [
            'status',
            'materialRequest.status',
            'materialRequest.items.product',
            'prItems.product',
            'prItems.lpoItems.lpo',
            'transactions.items.product',
            'transactions.status',
            'lpos.supplier',
            'lpos.status',
            'lpos.items.product'
        ];
    }

    private function formatPurchaseRequest($pr): array
    {
        $items = $pr->prItems->map(function ($prItem) {
            $totalReceived = $prItem->lpoItems->sum('received_quantity');

            $lpoBreakdown = $prItem->lpoItems->map(function ($lpoItem) {
                return [
                    'id' => $lpoItem->lpo_id,
                    'supplier_id' => $lpoItem->lpo->supplier_id ?? null,
                    'supplier' => $lpoItem->lpo->supplier ?? null,
                    'received_quantity' => $lpoItem->received_quantity,
                    'requested_quantity' => $lpoItem->requested_quantity
                ];
            });

            return [
                'id' => $prItem->id,
                'product' => $prItem->product,
                'quantity' => $prItem->quantity,
                'total_received' => $totalReceived,
                'lpos' => $lpoBreakdown
            ];
        });
        $stockTransfers =
            $pr->materialRequest->stockTransfers;

        $allStockItems = $stockTransfers
            ->pluck('items')
            ->flatten(1)
            ->groupBy('product_id');

        // Map each item and sum issued/received quantities
        $formattedMaterialRequest = [
            'id' => $pr->materialRequest->id,
            'material_request_number' => $pr->materialRequest->material_request_number,
            'status' => $pr->materialRequest->status,
            'items' => collect($pr->materialRequest->items)->map(function ($item) use ($allStockItems) {
                $stockGroup = $allStockItems->get($item->product_id);

                return [
                    'id' => $item->id,
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'requested_quantity' => $stockGroup ? $stockGroup->first()->requested_quantity ?? $item->quantity : $item->quantity,
                    'issued_quantity' => $stockGroup ? $stockGroup->sum('issued_quantity') : 0,
                    'received_quantity' => $stockGroup ? $stockGroup->sum('received_quantity') : 0,
                ];
            })->values()
        ];


        return [
            'id' => $pr->id,
            'purchase_request_number' => $pr->purchase_request_number,
            'material_request_id' => $pr->material_request_id,
            'material_request_number' => $pr->material_request_number,
            'status' => $pr->status,
            'material_request' => $formattedMaterialRequest,
            'transactions' => $pr->transactions,
            'items' => $items,
            'lpos' => $pr->lpos
        ];
    }


}
