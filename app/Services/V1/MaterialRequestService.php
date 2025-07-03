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
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\Stock;
use App\Models\V1\Store;
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
            $user = auth()->user();
            $materialRequest = MaterialRequest::findOrFail($id);
            $materialRequest->status_id = $request->status_id;

            // if (in_array($request->status_id, [5, 10])) {

            //     if (empty($request->items)) {
            //         throw new \Exception('Invalid items data');
            //     } else {
            //         $request->items = json_decode($request->items);
            //     }


            //     foreach ($request->items as $item) {
            //         if (!isset($item->issued_quantity)) {
            //             throw new \Exception('Missing quantity');
            //         }
            //     }

            //     $isCentralStore = (Store::find($request->from_store_id))->is_central_store;
            //     $stockTransfers = new StockTransfer();
            //     $stockTransfers->transaction_number = 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT);
            //     $stockTransfers->to_store_id = $materialRequest->store_id;
            //     $stockTransfers->from_store_id = $request->from_store_id;
            //     $stockTransfers->request_id = $id;
            //     $stockTransfers->remarks = $request->note;
            //     $stockTransfers->send_by = $user->id;
            //     $stockTransfers->request_type = "MR";
            //     $stockTransfers->transaction_type = $isCentralStore ? "CS-SS" : "SS-SS";
            //     $stockTransfers->dn_number = $request->dn_number;
            //     $stockTransfers->save();

            //     if (!empty($request->images) && is_array($request->images)) {
            //         foreach ($request->images as $image) {
            //             $stockTransferFile = new StockTransferFile();
            //             $mimeType = $image->getMimeType();
            //             $imagePath = Helpers::uploadFile($image, "files/stock-transfer/$stockTransfers->id");
            //             $stockTransferFile->file = $imagePath;
            //             $stockTransferFile->file_mime_type = $mimeType;
            //             $stockTransferFile->stock_transfer_id = $stockTransfers->id;
            //             $stockTransferFile->material_request_id = $materialRequest->id;
            //             $stockTransferFile->transaction_type = "transfer";
            //             $stockTransferFile->save();
            //         }
            //     }

            //     if (!empty($request->note)) {
            //         $stockTransferNote = new StockTransferNote();
            //         $stockTransferNote->stock_transfer_id = $stockTransfers->id;
            //         $stockTransferNote->material_request_id = $materialRequest->id;
            //         $stockTransferNote->notes = $request->note;
            //         $stockTransferNote->save();
            //     }

            //     foreach ($request->items as $item) {
            //         $product = Product::findOrFail($item->product_id);

            //         $transferItem = new StockTransferItem();
            //         $transferItem->stock_transfer_id = $stockTransfers->id;
            //         $transferItem->product_id = $item->product_id;
            //         $transferItem->requested_quantity = $item->requested_quantity;
            //         $transferItem->issued_quantity = $item->issued_quantity;
            //         $transferItem->save();
            //         $fromStockQuery = Stock::where('store_id', $request->from_store_id)
            //             ->where('product_id', $item->product_id);
            //         if (!$isCentralStore) {
            //             //get the from engineerid
            //             //$fromStockQuery->where('engineer_id', $engineerId);
            //         }

            //         $fromStock = $fromStockQuery->first();

            //         if ($fromStock) {
            //             if ($fromStock->quantity < $item->issued_quantity) {
            //                 throw new \Exception('Insufficient stock (' . $product->item . ')');
            //             }

            //             $fromStock->quantity -= $item->issued_quantity;
            //             $fromStock->save();
            //         } else {
            //             if ($item->issued_quantity > 0) {
            //                 throw new \Exception('Insufficient stock (' . $product->item . ')');
            //             }
            //         }

            //         $stockInTransit = new StockInTransit();
            //         $stockInTransit->stock_transfer_id = $stockTransfers->id;
            //         $stockInTransit->material_request_id = $materialRequest->id;
            //         $stockInTransit->stock_transfer_item_id = $transferItem->id;
            //         $stockInTransit->product_id = $item->product_id;
            //         $stockInTransit->issued_quantity = $item->issued_quantity;
            //         $stockInTransit->save();

            //         StockTransaction::create([
            //             'store_id' => $request->from_store_id,
            //             'product_id' => $item->product_id,
            //             'engineer_id' => $materialRequest->engineer_id,
            //             'quantity' => $item->issued_quantity,
            //             'stock_movement' => 'TRANSIT',
            //             'type' => 'MR',
            //             'dn_number' => $request->dn_number,
            //         ]);
            //     }
            // }
            $materialRequest->save();


            // if (in_array($request->status_id, [5, 9])) {
            //     $pr = PurchaseRequest::create([
            //         'purchase_request_number' => 'PR' . str_pad(PurchaseRequest::max('id') + 1001, 6, '0', STR_PAD_LEFT),
            //         'material_request_id' => $materialRequest->id,
            //         'material_request_number' => $materialRequest->request_number,
            //     ]);

            //     foreach ($materialRequest->items as $item) {
            //         $requested = $item->requested_quantity ?? $item->quantity;
            //         $issued = $item->issued_quantity ?? 0;
            //         if ($issued < $requested) {
            //             PurchaseRequestItem::create([
            //                 'purchase_request_id' => $pr->id,
            //                 'material_request_item_id' => $item->id,
            //                 'product_id' => $item->product_id,
            //                 'quantity' => $requested - $issued,
            //             ]);
            //         }
            //     }
            // }
            \DB::commit();
            return $materialRequest;
        } catch (\Throwable $th) {
            \DB::rollBack();
            throw $th;
        }

    }


    public function mapStockItemsProduct($materialRequest)
    {

        $stockItems = collect($materialRequest->stockTransfers ?? [])
            ->pluck('items')
            ->flatten(1)
            ->groupBy('product_id');
        $materialRequestItems = collect($materialRequest->items)->map(function ($item) use ($stockItems) {
            $group = $stockItems->get($item->product_id);

            $item->requested_quantity = $group ? $group->first()->requested_quantity ?? $item->quantity : $item->quantity;
            $item->issued_quantity = $group ? $group->sum('issued_quantity') : null;
            $item->received_quantity = $group ? $group->sum('received_quantity') : null;

            return $item;
        });
        $materialRequest = $materialRequest->setRelation('items', $materialRequestItems);
        return $materialRequest;
    }
}
