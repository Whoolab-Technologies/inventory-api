<?php

namespace App\Http\Controllers\V1;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\LpoShipment;
use App\Models\V1\LpoShipmentItem;
use App\Models\V1\MaterialRequestItem;
use App\Models\V1\Lpo;
use App\Services\Helpers;
use App\Services\V1\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
            return Helpers::sendResponse(500, null, 'Error updating purchase request: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        try {
            $purchaseRequests = PurchaseRequest::with([
                'status',
                'prItems.product',
                'lpos.status'
            ])
                ->orderByDesc('id')
                ->get();
            foreach ($purchaseRequests as $request) {
                $request->items = $request->prItems;
                unset($request->prItems);
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
            'lpos.shipments.status'
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
                'lpo' => $this->formatLpo($lpo)
            ];
            \Log::info("Return resposne", ["Purchase Request" => $purchaseRequest, "lpo" => $this->formatLpo($lpo)]);
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
            // $purchaseRequest = PurchaseRequest::with([
            //     'lpos.shipments' => function ($query) {
            //         $query->where('status_id', StatusEnum::ON_HOLD);
            //     }
            // ])->findOrFail($id);
            $purchaseRequest = PurchaseRequest::with([
                'lpos' => function ($query) {
                    $query->whereHas('shipments', function ($q) {
                        $q->where('status_id', StatusEnum::ON_HOLD);
                    })->with([
                                'shipments' => function ($q) {
                                    $q->where('status_id', StatusEnum::ON_HOLD);
                                }
                            ]);
                }
            ])->findOrFail($id);
            $shipments = $purchaseRequest->lpos->flatMap->shipments;
            $materialRequest = $purchaseRequest->materialRequest;
            $this->purchaseRequestService->updateOnHoldSupplierToCentralTransctions(
                $shipments,
                $materialRequest
            );
            $shipmentItems = collect($shipments)->flatMap(function ($shipment) {
                return $shipment->items;
            })->groupBy('product_id')->map(function ($items) {
                return (object) [
                    'product_id' => $items->first()->product_id,
                    'quantity_delivered' => $items->sum('quantity_delivered')
                ];
            })->values();

            $this->purchaseRequestService->updateOnHoldCentralToSiteTransctions(
                $shipmentItems,
                $materialRequest,
                $request->dn_number
            );
            $shipments->each(function ($shipment) {
                $shipment->status_id = 7;
                $shipment->save();
            });

            $purchaseRequest->lpos->each(function ($lpo) {
                $lpo->status_id = 7;
                $lpo->save();
            });
            $purchaseRequest = $this->getFormatedPurchaseRequest($id);
            $response['purchaseRequest'] = $purchaseRequest;
            \Log::info("Return resposne", ["Purchase Request" => $purchaseRequest,]);

            \DB::commit();
            return Helpers::sendResponse(200, $response, '');

        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::info("BULK TRANSFER FAILED", ["ERROR" => $e->getMessage()]);

            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

}