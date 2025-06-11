<?php

namespace App\Services\V1;

use App\Models\V1\MaterialRequest;
use App\Models\V1\MaterialRequestStockTransfer;
use App\Models\V1\Product;
use App\Models\V1\StockInTransit;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferFile;
use App\Models\V1\StockTransferItem;
use App\Models\V1\StockTransferNote;
use App\Models\V1\StockTransaction;
use App\Models\V1\Stock;
use Illuminate\Http\Request;
use App\Services\Helpers;
class MaterialRequestService
{
    /**
     * Get material requests for an engineer that are not approved.
     */
    public function getPendingRequestsForEngineer($engineerId)
    {
        return MaterialRequest::where('engineer_id', $engineerId)
            ->where('status', '!=', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Approve a material request.
     */
    public function approveRequest($requestId)
    {
        $materialRequest = MaterialRequest::findOrFail($requestId);
        $materialRequest->status = 'approved';
        $materialRequest->save();

        return $materialRequest;
    }

    /**
     * Create a new material request.
     */
    public function createMaterialRequest($data)
    {
        return MaterialRequest::create($data);
    }

    public function updateMaterialRequest(Request $request, int $id)
    {
        \DB::beginTransaction();
        try {
            $materialRequest = MaterialRequest::findOrFail($id);
            $materialRequest->status = $request->status;


            if ($request->status == 'completed') {

                if (empty($request->items)) {
                    throw new \Exception('Invalid items data');
                } else {
                    $request->items = json_decode($request->items);
                }


                foreach ($request->items as $item) {
                    if (!isset($item->issued_quantity)) {
                        throw new \Exception('Missing quantity');
                    }
                }


                $stockTransfers = new StockTransfer();
                $stockTransfers->to_store_id = $materialRequest->store_id;
                $stockTransfers->from_store_id = $request->from_store_id;
                $stockTransfers->status = "in_transit";
                $stockTransfers->remarks = $request->note;
                $stockTransfers->dn_number = $request->dn_number;
                $stockTransfers->save();

                if (!empty($request->images) && is_array($request->images)) {
                    foreach ($request->images as $image) {
                        $stockTransferFile = new StockTransferFile();
                        $mimeType = $image->getMimeType();
                        $imagePath = Helpers::uploadFile($image, "files/stock-transfer/$stockTransfers->id");

                        $stockTransferFile->file = $imagePath;
                        $stockTransferFile->file_mime_type = $mimeType;
                        $stockTransferFile->stock_transfer_id = $stockTransfers->id;
                        $stockTransferFile->material_request_id = $materialRequest->id;
                        $stockTransferFile->transaction_type = "transfer";
                        $stockTransferFile->save();
                    }

                }

                if (!empty($request->note)) {
                    $stockTransferNote = new StockTransferNote();
                    $stockTransferNote->stock_transfer_id = $stockTransfers->id;
                    $stockTransferNote->material_request_id = $materialRequest->id;
                    $stockTransferNote->notes = $request->note;
                    $stockTransferNote->save();
                }
                $materialRequestStockTransfer = new MaterialRequestStockTransfer();
                $materialRequestStockTransfer->stock_transfer_id = $stockTransfers->id;
                $materialRequestStockTransfer->material_request_id = $materialRequest->id;
                $materialRequestStockTransfer->save();

                foreach ($request->items as $item) {
                    $product = Product::findOrFail($item->product_id);

                    $transferItem = new StockTransferItem();
                    $transferItem->stock_transfer_id = $stockTransfers->id;
                    $transferItem->product_id = $item->product_id;
                    $transferItem->requested_quantity = $item->requested_quantity;
                    $transferItem->issued_quantity = $item->issued_quantity;
                    $transferItem->save();

                    $fromStock = Stock::where('store_id', $request->from_store_id)
                        ->where('product_id', $item->product_id)
                        ->first();

                    if ($fromStock) {
                        $fromStock->quantity -= $item->issued_quantity;
                        if ($fromStock->quantity < 0) {
                            throw new \Exception('Insufficient stock (' . $product->item . ')');
                        }
                        $fromStock->save();
                    } else {
                        throw new \Exception('Insufficient stock (' . $product->item . ')');
                    }

                    $stockInTransit = new StockInTransit();
                    $stockInTransit->stock_transfer_id = $stockTransfers->id;
                    $stockInTransit->material_request_id = $materialRequest->id;
                    $stockInTransit->stock_transfer_item_id = $transferItem->id;
                    $stockInTransit->product_id = $item->product_id;
                    $stockInTransit->issued_quantity = $item->issued_quantity;
                    $stockInTransit->save();

                    StockTransaction::create([
                        'store_id' => $request->from_store_id,
                        'product_id' => $item->product_id,
                        'engineer_id' => $materialRequest->engineer_id,
                        'quantity' => $item->issued_quantity,
                        'stock_movement' => 'TRANSIT',
                        'type' => 'TRANSFER',
                        'dn_number' => $request->dn_number,
                    ]);
                }
            }
            $materialRequest->save();
            \DB::commit();
            return $materialRequest;
        } catch (\Throwable $th) {
            \DB::rollBack();
            throw $th;
        }

    }
}
