<?php

namespace App\Http\Controllers\V1;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\MaterialRequestStock;
use App\Models\V1\LpoShipment;
use App\Models\V1\Lpo;
use App\Services\Helpers;
use App\Services\V1\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
class PurchaseRequestController extends Controller
{
    protected $purchaseRequestService;

    public function __construct(
        PurchaseRequestService $purchaseRequestService,
    ) {
        $this->purchaseRequestService = $purchaseRequestService;
    }
    public function update(Request $request, $id)
    {
        \DB::beginTransaction();
        try {

            $purchaseRequest = PurchaseRequest::findOrFail($id);

            $purchaseRequest->status_id = $request->input('status_id', $purchaseRequest->status_id);
            $purchaseRequest->save();
            $materialRequest = $purchaseRequest->materialRequest;
            $latestStockTransfer = $materialRequest->stockTransfers()->latest()->first();
            if ($latestStockTransfer) {
                $materialRequest->status_id = $latestStockTransfer->status_id;
            } else {
                $materialRequest->status_id = $purchaseRequest->status_id;
            }
            $materialRequest->description = "Material Request status changed as a result of Purchase Request being rejected.";
            $materialRequest->save();
            MaterialRequestStock::where('purchase_request_id', $id)
                ->delete();
            $pr = PurchaseRequest::with($this->prRelations())->findOrFail($id);
            $response = $this->formatPurchaseRequest($pr);
            \DB::commit();
            return Helpers::sendResponse(200, $response, 'Purchase Request updated successfully');
        } catch (ModelNotFoundException $e) {
            \DB::rollBack();
            return Helpers::sendResponse(404, [], 'Purchase request not found');
        } catch (\Throwable $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, null, 'Error updating purchase request: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {


        $search = $request->input('search');
        $statusId = $request->input('status_id');
        $storeId = $request->input('store_id');
        $engineerId = $request->input('engineer_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');   // string (Y-m-d) or null

        try {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            if ($dateFrom) {
                $dateFrom = Carbon::parse($dateFrom)->format('Y-m-d');
            }

            if ($dateTo) {
                $dateTo = Carbon::parse($dateTo)->format('Y-m-d');
            }

            $purchaseRequests = PurchaseRequest::with([
                'status',
                'prItems.product',
                'lpos.status'
            ])
                ->search($search, $statusId, $dateFrom, $dateTo, $storeId, $engineerId)
                ->orderByDesc('id')
                ->get();

            foreach ($purchaseRequests as $request) {
                $request->items = $request->prItems;
                $request->store = $request->store();
                $request->engineer = $request->engineer();
                unset($request->prItems, $request->materialRequest);
            }


            // $purchaseRequests = $purchaseRequests->map(fn($pr) => $this->formatPurchaseRequest($pr));

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

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(404, null, 'Purchase request not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, $e->getMessage());
        }
    }


    public function createLpo(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'lpo_number' => 'required|string',
                'pr_id' => 'required|integer|exists:purchase_requests,id',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.requested_quantity' => 'required|numeric|min:1',
            ]);

            $lpo = $this->purchaseRequestService->createLpoWithItems($request);
            $pr = PurchaseRequest::with($this->prRelations())->findOrFail($id);
            $response = $this->formatPurchaseRequest($pr);
            return Helpers::sendResponse(200, $response, 'Lpo created successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, $e->getMessage());
        }
    }

    private function prRelations(): array
    {
        return [
            'status',
            'materialRequest.status',
            'prItems.product',
            'prItems.lpoItems.lpo',
            'lpos.supplier',
            'lpos.status',
            'lpos.items.product',
            'lpos.shipments.status',
            'lpos.shipments.items.product'
        ];
    }

    private function formatPurchaseRequest($pr): array
    {
        $items = $pr->prItems->map(function ($prItem) {
            $totalReceived = $prItem->lpoItems->sum('received_quantity');
            $totalRequested = $prItem->lpoItems->sum('requested_quantity');

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
                'total_requested' => $totalRequested,
                'lpos' => $lpoBreakdown
            ];
        });
        $materialRequest = $pr->materialRequest;
        $stockTransfers =
            $materialRequest->stockTransfers;

        $allStockItems = $stockTransfers
            ->pluck('items')
            ->flatten(1)
            ->groupBy('product_id');

        // Map each item and sum issued/received quantities
        $formattedMaterialRequest = [
            'id' => $materialRequest->id,
            'request_number' => $materialRequest->request_number,
            'created_at' => $materialRequest->created_at,
            'status' => $materialRequest->status,
        ];


        return [
            'id' => $pr->id,
            'status_id' => $pr->status_id,
            'purchase_request_number' => $pr->purchase_request_number,
            'material_request_id' => $pr->material_request_id,
            'material_request_number' => $pr->material_request_number,

            'status' => $pr->status,
            'material_request' => $formattedMaterialRequest,
            'items' => $items,
            'lpos' => $pr->lpos,
            // 'has_on_hold_shipment' => $pr->has_on_hold_shipment,
        ];
    }


    public function getLpo(Request $request, $id)
    {
        try {
            $lpo = Lpo::with(['items.product', 'supplier', 'status', 'shipments.status'])
                ->findOrFail($id);

            $response['lpo'] = $this->formatLpo($lpo);
            return Helpers::sendResponse(200, $response, 'LPO details retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving LPO: ' . $e->getMessage());
        }
    }
    public function getLpos(Request $request, )
    {
        try {
            $query = Lpo::with(['items.product', 'supplier', 'status', 'shipments.status']);

            if ($request->has('pr_id')) {
                $query->where('pr_id', $request->input('pr_id'));
            }

            if ($request->has('shipment_status_id')) {
                $statusId = $request->input('shipment_status_id');
                $query->whereHas('shipments', function ($q) use ($statusId) {
                    $q->where('status_id', $statusId);
                });
            }

            $lpos = $query->get();
            $lpos = $lpos->map(function ($lpo) {
                return $this->formatLpo($lpo);
            });
            return Helpers::sendResponse(200, $lpos, 'LPO details retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving LPO: ' . $e->getMessage());
        }
    }

    protected function formatLpo($lpo)
    {

        $lpoItems = $lpo->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product' => $item->product,
                'requested_quantity' => $item->requested_quantity,
                'received_quantity' => $item->received_quantity,
            ];
        });

        return [
            'id' => $lpo->id,
            'lpo_number' => $lpo->lpo_number,
            'supplier' => $lpo->supplier,
            'status' => $lpo->status,
            'date' => $lpo->date,
            'shipments' => $lpo->shipments,
            'items' => $lpoItems,
        ];
    }

    public function storeLpoShipment(Request $request, $id)
    {
        $this->purchaseRequestService->validateShipmentRequest($request);

        \DB::beginTransaction();

        try {
            // 1. Create shipment
            $shipment = $this->purchaseRequestService->createLpoShipment($request);
            $this->purchaseRequestService->createShipmentItems($shipment->id, $request->items);
            $this->purchaseRequestService->updateLpoStatusIfAllItemsReceived($request->lpo_id);

            if ($shipment->status_id == StatusEnum::IN_TRANSIT) {
                $this->purchaseRequestService->createShipmentTransaction($shipment, $request->dn_number);
                $shipment->status_id = StatusEnum::COMPLETED;
                $shipment->save();
            }

            $shipment->load(['items', 'status']);
            // 2. Fetch updated LPO with required relations
            $lpo = Lpo::with([
                'items.product',
                'supplier',
                'status',
                'shipments.status'
            ])->findOrFail($id);

            $purchaseRequest = $this->getFormatedPurchaseRequest($lpo->pr_id);
            $response = [
                'purchaseRequest' => $purchaseRequest,
                'lpo' => $this->formatLpo($lpo),
                'shipment' => $shipment
            ];
            \DB::commit();
            return Helpers::sendResponse(200, $response, 'Shipment created successfully');

        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::info($e->getMessage());
            return Helpers::sendResponse(500, $e->getMessage());
        }
    }

    protected function getFormatedPurchaseRequest($id)
    {
        $pr = $this->purchaseRequestService->updatePurchaseRequestStatusComplete($id);
        $pr->load($this->prRelations());
        // 6. Format response
        $purchaseRequest = $this->formatPurchaseRequest($pr);
        $purchaseRequest['has_on_hold_shipment'] = $pr->has_on_hold_shipment;
        return $purchaseRequest;
    }
    public function completeOnHoldShipments(Request $request, $id)
    {
        \DB::beginTransaction();
        $response = [];
        try {
            $data = $this->purchaseRequestService->getOnHoldShipments($id);
            $purchaseRequest = $data['purchaseRequest'];
            $shipments = $data['shipments'];
            $materialRequest = $purchaseRequest->materialRequest;
            $this->purchaseRequestService->updateOnHoldSupplierToCentralTransctions(
                $shipments,
                $materialRequest
            );
            $shipmentItems = $this->purchaseRequestService->getShipmentItems($shipments);

            $this->purchaseRequestService->updateOnHoldCentralToSiteTransactions(
                $shipmentItems,
                $materialRequest,
                $request
            );
            $shipments->each(function ($shipment) {
                $shipment->status_id = 7;
                $shipment->save();
            });

            $purchaseRequest->lpos->each(function ($lpo) {
                $lpo->status_id = 7;
                $lpo->save();
            });
            $materialRequest->status_id = StatusEnum::IN_TRANSIT->value;
            $materialRequest->save();
            $purchaseRequest = $this->getFormatedPurchaseRequest($id);
            MaterialRequestStock::where('purchase_request_id', $id)
                ->delete();
            $response['purchaseRequest'] = $purchaseRequest;
            \DB::commit();
            return Helpers::sendResponse(200, $response, '');
        } catch (\Throwable $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }


    public function getShipment(Request $request, $id)
    {
        try {
            $shipment = LpoShipment::with(['status', 'items.product'])->findOrFail($id);
            return Helpers::sendResponse(200, $shipment, '');
        } catch (\Throwable $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    public function updateShipment(Request $request, $id)
    {
        \DB::beginTransaction();

        try {
            $shipment = LpoShipment::with(['lpo.purchaseRequest.materialRequest', 'items'])->findOrFail($id);

            $lpo = $shipment->lpo;
            $purchaseRequest = $lpo->purchaseRequest;
            $materialRequest = $purchaseRequest->materialRequest;

            $this->purchaseRequestService->updateOnHoldSupplierToCentralTransctions(
                $shipment,
                $materialRequest
            );

            $this->purchaseRequestService->updateOnHoldCentralToSiteTransactions(
                $shipment->items,
                $materialRequest,
                $request
            );

            $lpo->status_id = StatusEnum::COMPLETED->value;
            $shipment->status_id = StatusEnum::COMPLETED->value;
            $materialRequest->status_id = StatusEnum::IN_TRANSIT->value;

            $lpo->save();
            $shipment->save();
            $materialRequest->save();

            $this->purchaseRequestService->updatePurchaseRequestStatusComplete($purchaseRequest->id);

            \DB::commit();

            return Helpers::sendResponse(200, $shipment->load(['status', 'items.product']), '');

        } catch (\Throwable $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }


    public function getOnHoldShipments(Request $request, $id)
    {
        \DB::beginTransaction();
        try {
            $data = $this->purchaseRequestService->getOnHoldShipments($id);
            $shipments = $data['shipments'];
            $shipmentItems = $this->purchaseRequestService->getShipmentItems($shipments);
            return Helpers::sendResponse(200, $shipmentItems, '');
        } catch (\Throwable $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function updateMaterialRequestStock(Request $request, $id)
    {
        try {
            $shipment = LpoShipment::with(['items.product'])->findOrFail($id);
            $lpo = $shipment->lpo;
            $purchaseRequest = $lpo->purchaseRequest;
            $materialRequest = $purchaseRequest->materialRequest;
            $materialRequestId = $materialRequest->id;
            $purchaseRequestId = $purchaseRequest->id;

            foreach ($shipment->items as $item) {
                $productId = $item->product_id;
                $quantityToAdd = $item->quantity_delivered;

                $stock = MaterialRequestStock::where('material_request_id', $materialRequestId)
                    ->where('product_id', $productId)
                    ->where('purchase_request_id', $purchaseRequestId)
                    ->first();

                if ($stock) {
                    // Increment existing quantity
                    $stock->increment('quantity', $quantityToAdd);
                } else {
                    $stock = new MaterialRequestStock();

                    $stock->material_request_id = $materialRequestId;
                    $stock->purchase_request_id = $purchaseRequestId;
                    $stock->product_id = $productId;
                    $stock->quantity = $quantityToAdd;
                    $stock->save();
                }
            }
            \DB::commit();
            return Helpers::sendResponse(200, $shipment, '');
        } catch (\Throwable $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}