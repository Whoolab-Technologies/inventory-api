<?php

namespace App\Services\V1;

use App\Data\StockTransferData;
use App\Models\V1\Stock;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransaction;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\Store;
use App\Enums\TransferPartyRole;
use App\Data\StockTransactionData;
use App\Data\StockInTransitData;
use App\Data\MaterialReturnData;
class StockTransferService
{

    public function createMaterialReturn(MaterialReturnData $data)
    {
        $materialReturn = new MaterialReturn();
        $materialReturn->from_store_id = $data->fromStoreId;
        $materialReturn->to_store_id = $data->toStoreId;
        $materialReturn->dn_number = $data->dnNumber ?? null;
        $materialReturn->save();

        foreach ($data->items as $item) {
            foreach ($item['engineers'] as $engineer) {

                $materialReturnDetail = new MaterialReturnDetail();
                $materialReturnDetail->material_return_id = $materialReturn->id;
                $materialReturnDetail->engineer_id = $engineer['engineer_id'];
                $materialReturnDetail->save();

                $materialReturnItem = new MaterialReturnItem();
                $materialReturnItem->material_return_id = $materialReturn->id;
                $materialReturnItem->material_return_detail_id = $materialReturnDetail->id;
                $materialReturnItem->product_id = $item['product_id'];
                $materialReturnItem->issued = $item['issued'];
                $materialReturnItem->save();
            }
        }

        return $materialReturn;
    }


    public function createStockInTransit(StockInTransitData $data)
    {
        \Log::info("StockInTransitData ", ['data' => $data]);
        $stockInTransit = new StockInTransit();
        $stockInTransit->stock_transfer_id = $data->stockTransferId;
        $stockInTransit->material_request_id = $data->materialRequestId;
        $stockInTransit->stock_transfer_item_id = $data->stockTransferItemId;
        $stockInTransit->material_request_item_id = $data->materialRequestItemId;
        $stockInTransit->product_id = $data->productId;
        $stockInTransit->issued_quantity = $data->issuedQuantity;
        $stockInTransit->save();

        return $stockInTransit;
    }

    public function createStockTransaction(StockTransactionData $data)
    {
        return StockTransaction::create([
            'store_id' => $data->storeId,
            'product_id' => $data->productId,
            'engineer_id' => $data->engineerId,
            'quantity' => abs($data->quantityChange),
            'stock_movement' => $data->movement,
            'type' => $data->type,
            'lpo' => $data->lpo,
            'dn_number' => $data->dnNumber,
        ]);
    }

    public function updateStock($storeId, $productId, $quantityChange, $engineerId = 0)
    {
        $store = Store::findOrFail($storeId);

        if ($store->is_central_store) {
            $engineerId = 0;
        }
        $stock = Stock::firstOrNew([
            'store_id' => $storeId,
            'product_id' => $productId,
            'engineer_id' => $engineerId,
        ]);

        if (!$stock->exists) {
            $stock->quantity = 0;
        }

        $stock->quantity += $quantityChange;
        $stock->save();

        return $stock;
    }
    public function getOrCreateStock($storeId, $productId, $engineerId = 0)
    {
        $stock = Stock::where('product_id', $productId)
            ->where('engineer_id', $engineerId)
            ->first();

        if (!$stock) {
            $stock = Stock::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'engineer_id' => $engineerId,
                'quantity' => 0,
            ]);
        }

        return $stock;
    }

    public function getStock($productId, $engineerId = 0)
    {
        $stock = Stock::where('product_id', $productId)
            ->where('engineer_id', $engineerId)
            ->first();

        return $stock;
    }


    public function createStockTransferItem($stockTransferId, $productId, $requestedQuantity, $issuedQuantity)
    {
        return StockTransferItem::create([
            'stock_transfer_id' => $stockTransferId,
            'product_id' => $productId,
            'requested_quantity' => $requestedQuantity,
            'issued_quantity' => $issuedQuantity,
        ]);
    }

    public function updateStockTransferItem($stockTransferItemId, $receivedQuantity)
    {
        $item = StockTransferItem::findOrFail($stockTransferItemId);

        $item->received_quantity = $receivedQuantity;
        $item->save();

        return $item;
    }

    public function updateStockTransfer($stockTransferId, $statusId, $receiver, TransferPartyRole $receiverRole)
    {
        $stockTransfer = StockTransfer::findOrFail($stockTransferId);

        $stockTransfer->status_id = $statusId;
        $stockTransfer->received_by = $receiver;
        $stockTransfer->receiver_role = $receiverRole->value;
        $stockTransfer->save();
        return $stockTransfer;
    }
    public function createStockTransfer(StockTransferData $data)
    {
        \Log::info('Check Request Type', ['request_type' => $data->requestType]);
        return StockTransfer::create([
            'transaction_number' => 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT),
            'from_store_id' => $data->fromStoreId,
            'to_store_id' => $data->toStoreId,
            'status_id' => $data->statusId,
            'dn_number' => $data->dnNumber,
            'remarks' => $data->remarks,
            'request_id' => $data->requestId,
            'request_type' => $data->requestType,
            'transaction_type' => $data->transactionType,
            'send_by' => $data->sendBy,
            'sender_role' => $data->senderRole,
            'received_by' => $data->receivedBy,
            'receiver_role' => $data->receiverRole,
            'note' => $data->note,
        ]);
    }
}