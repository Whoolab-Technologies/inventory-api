<?php

namespace App\Services\V1;

use App\Data\StockTransferData;
use App\Models\V1\Stock;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransaction;
use App\Enums\TransferPartyRole;
use App\Data\StockTransactionData;
class StockTransferService
{
    public function createStockTransaction(StockTransactionData $data)
    {
        return StockTransaction::create([
            'store_id' => $data->storeId,
            'product_id' => $data->productId,
            'engineer_id' => $data->engineerId,
            'quantity' => abs($data->quantityChange),
            'stock_movement' => $data->movementType,
            'type' => $data->type,
            'lpo' => $data->lpo,
            'dn_number' => $data->dnNumber,
        ]);
    }

    public function updateStock($storeId, $productId, $quantityChange, $engineerId = 0)
    {
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

        $item->issued_quantity = $receivedQuantity;
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