<?php
namespace App\Services\V1;


use App\Models\V1\Lpo;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\MaterialRequestItem;
use App\Models\V1\Store;
use App\Models\V1\LpoItem;
use App\Models\V1\LpoShipmentItem;
use App\Models\V1\LpoShipment;

use App\Enums\StatusEnum;
use App\Enums\StockMovementType;
use App\Enums\StockMovement;
use App\Enums\RequestType;
use App\Enums\TransactionType;
use App\Enums\TransferPartyRole;

use App\Data\PurchaseRequestData;
use App\Data\StockTransactionData;
use App\Data\StockTransferData;
use App\Data\StockInTransitData;

use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class PurchaseRequestService
{

    protected $stockTransferService;

    public function __construct(
        StockTransferService $stockTransferService,
    ) {
        $this->stockTransferService = $stockTransferService;
    }

    public function createPurchaseRequest(PurchaseRequestData $data)
    {
        $purchaseRequest = PurchaseRequest::create([
            'purchase_request_number' => 'PR-' . date('Y') . '-' . str_pad(PurchaseRequest::max('id') + 1, 3, '0', STR_PAD_LEFT),

            'material_request_id' => $data->materialRequestId,
            'material_request_number' => $data->materialRequestNumber,
            'status_id' => $data->statusId,
        ]);

        $materialRequestItems = MaterialRequestItem::where('material_request_id', $data->materialRequestId)
            ->get()
            ->keyBy('product_id');

        foreach ($data->items as $item) {
            if (!isset($materialRequestItems[$item['product_id']])) {
                throw ValidationException::withMessages([
                    'material_request_item' => "Material request item not found for product ID {$item['product_id']}."
                ]);
            }

            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'material_request_item_id' => $materialRequestItems[$item['product_id']]->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['missing_quantity'],
            ]);
        }
        return $purchaseRequest;


    }



    public function createLpoWithItems(Request $request)
    {
        try {


            $lpo = Lpo::create([
                'lpo_number' => $request->lpo_number,
                'pr_id' => $request->pr_id,
                'supplier_id' => $request->supplier_id,
                'date' => \Carbon\Carbon::parse($request->date ?? now())->format('Y-m-d'),
                'status_id' => StatusEnum::PENDING
            ]);

            PurchaseRequest::where('id', $request->pr_id)
                ->update(['status_id' => StatusEnum::PROCESSING]);

            $itemsRelation = $lpo->items();
            collect($request->items)
                ->filter(fn($item) => $item['requested_quantity'] > 0)
                ->each(function ($item) use ($lpo, $request, $itemsRelation) {
                    $itemsRelation->create([
                        'lpo_id' => $lpo->id,
                        'pr_id' => $request->pr_id,
                        'pr_item_id' => $item['item_id'],
                        'product_id' => $item['product_id'],
                        'requested_quantity' => $item['requested_quantity'],
                    ]);
                });
            $lpo->load('items');
            return $lpo;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createShipmentTransaction($shipment)
    {

        $centralStore = Store::where('type', 'central')->firstOrFail();
        $fromStoreId = $centralStore->id;
        $shipmentItems = $shipment->items;
        $lpo = $shipment->lpo;
        $materialRequest = $lpo->purchaseRequest->materialRequest;
        $engineerId = $materialRequest->engineer_id;
        $toStoreId = $materialRequest->store_id;

        $stockTransfer = $this->createStockTransfer($shipment, $materialRequest, $fromStoreId, $toStoreId);
        foreach ($shipmentItems as $shipmentItem) {
            $this->processShipmentItem($shipmentItem, $centralStore, $lpo, $shipment, $materialRequest, $stockTransfer, $engineerId);
        }
    }


    public function createStockTransfer($shipment, $materialRequest, $fromStoreId, $toStoreId)
    {
        $stockTransferData = new StockTransferData(
            $fromStoreId,
            $toStoreId,
            StatusEnum::IN_TRANSIT,
            $shipment->dn_number,
            null,
            $materialRequest->id,
            RequestType::PR,
            TransactionType::CS_SS,
            auth()->id(),
            TransferPartyRole::CENTRAL_STORE
        );

        return $this->stockTransferService->createStockTransfer($stockTransferData);
    }

    public function processShipmentItem($shipmentItem, $centralStore, $lpo, $shipment, $materialRequest, $stockTransfer, $engineerId)
    {
        $productId = $shipmentItem->product_id;
        $quantity = $shipmentItem->quantity_delivered;

        // Stock Transactions: IN & TRANSIT
        $this->createStockTransaction($centralStore->id, $productId, $engineerId, $quantity, $lpo->lpo_number, $shipment->dn_number, StockMovement::IN);
        $this->createStockTransaction($centralStore->id, $productId, $engineerId, $quantity, $lpo->lpo_number, $shipment->dn_number, StockMovement::TRANSIT);

        // Update Stock: IN then OUT
        $this->stockTransferService->updateStock($centralStore->id, $productId, $quantity);
        $this->stockTransferService->updateStock($centralStore->id, $productId, -abs($quantity));

        // Create Transfer Item
        $transferItem = $this->stockTransferService->createStockTransferItem(
            $stockTransfer->id,
            $productId,
            $quantity,
            $quantity
        );

        // Create Stock In Transit
        $materialRequestItem = MaterialRequestItem::where('material_request_id', $materialRequest->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $this->stockTransferService->createStockInTransit(new StockInTransitData(
            stockTransferId: $stockTransfer->id,
            stockTransferItemId: $transferItem->id,
            productId: $productId,
            issuedQuantity: $quantity,
            materialRequestId: $materialRequest->id,
            materialRequestItemId: $materialRequestItem->id,
            materialReturnId: null,
            materialReturnItemId: null
        ));
    }

    public function createStockTransaction($storeId, $productId, $engineerId, $quantity, $lpoNumber, $dnNumber, $movement)
    {
        $stockTransactionData = new StockTransactionData(
            $storeId,
            $productId,
            $engineerId,
            $quantity,
            StockMovementType::PR,
            $movement,
            $lpoNumber,
            $dnNumber
        );

        $this->stockTransferService->createStockTransaction($stockTransactionData);
    }



    public function validateShipmentRequest(Request $request)
    {
        $request->validate([
            'lpo_id' => 'required|exists:lpos,id',
            'dn_number' => 'required|string|max:255|unique:lpo_shipments,dn_number',
            'remarks' => 'nullable|string|max:255',
            'initial_immediate_transfer' => 'required|boolean',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:lpo_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.received_quantity' => 'required|numeric|min:0',
        ]);
    }

    public function createLpoShipment(Request $request)
    {
        return LpoShipment::create([
            'lpo_id' => $request->lpo_id,
            'dn_number' => $request->dn_number,
            'remarks' => $request->remarks,
            'date' => $request->date,
            'status_id' => $request->initial_immediate_transfer ? StatusEnum::IN_TRANSIT : StatusEnum::ON_HOLD,
        ]);
    }

    public function createShipmentItems($shipmentId, $items)
    {
        foreach ($items as $item) {
            LpoShipmentItem::create([
                'lpo_shipment_id' => $shipmentId,
                'lpo_item_id' => $item['item_id'],
                'product_id' => $item['product_id'],
                'quantity_delivered' => $item['received_quantity'],
            ]);

            $lpoItem = LpoItem::findOrFail($item['item_id']);
            $lpoItem->received_quantity = ($lpoItem->received_quantity ?? 0) + $item['received_quantity'];
            $lpoItem->save();
        }
    }

    public function updateLpoStatusIfAllItemsReceived($lpoId)
    {
        $pendingItems = LpoItem::where('lpo_id', $lpoId)
            ->whereRaw('IFNULL(received_quantity, 0) < requested_quantity')
            ->exists();

        if (!$pendingItems) {
            Lpo::where('id', $lpoId)->update(['status_id' => StatusEnum::COMPLETED->value]);
        }
    }

}