<?php

namespace App\Services\V1;

use App\Models\V1\MaterialRequest;
use App\Models\V1\MaterialRequestStockTransfer;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
use Illuminate\Http\Request;

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
            \Log::info("request  status " . $request->status);
            $materialRequest->status = $request->status;

            \Log::info("materialRequest  status " . json_encode($materialRequest));
            \Log::info("materialRequest  status " . json_encode($request->items));

            if ($request->status == 'completed') {

                // Create transaction with transaction items
                // Validate the item count
                if (empty($request->items) || !is_array($request->items)) {
                    throw new \Exception('Invalid items data');
                }

                foreach ($request->items as $item) {
                    if (!isset($item['quantity'])) {
                        throw new \Exception('Invalid item data');
                    }
                }

                // Create transaction with transaction items
                $stockTransfers = new StockTransfer();
                $stockTransfers->to_store_id = $materialRequest->store_id;
                $stockTransfers->from_store_id = $request->from_store_id;
                $stockTransfers->status = "in_transit";
                $stockTransfers->remarks = $request->note;
                $stockTransfers->save();

                $materialRequestStockTransfer = new MaterialRequestStockTransfer();
                $materialRequestStockTransfer->stock_transfer_id = $stockTransfers->id;
                $materialRequestStockTransfer->material_request_id = $materialRequest->id;
                $materialRequestStockTransfer->save();

                foreach ($request->items as $item) {
                    $transferItem = new StockTransferItem();
                    $transferItem->stock_transfer_id = $stockTransfers->id;
                    $transferItem->product_id = $item['product_id'];
                    $transferItem->quantity = $item['quantity'];
                    $transferItem->save();
                }
            }
            $materialRequest->save();
            \DB::commit();
            return $materialRequest;
        } catch (\Throwable $th) {
            \Log::info($th->getMessage());
            \DB::rollBack();
            throw $th;
        }

    }
}
