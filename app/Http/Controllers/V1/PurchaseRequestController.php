<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Services\Helpers;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseRequestController extends Controller
{
    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'lpo' => 'nullable|string',
                'do' => 'nullable|string',
                'status' => 'required|string',
                'items' => 'required|array',
                'items.*.id' => 'required|integer|exists:purchase_request_items,id',
                'items.*.quantity' => 'required|numeric|min:0',
            ]);

            $purchaseRequest = PurchaseRequest::findOrFail($id);

            $purchaseRequest->lpo = $data['lpo'];
            $purchaseRequest->do = $data['do'];
            $purchaseRequest->status = $data['status'];
            $purchaseRequest->save();

            // Update each item's quantity
            foreach ($data['items'] as $itemData) {
                PurchaseRequestItem::where('id', $itemData['id'])
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->update([
                        'quantity' => $itemData['quantity'],
                    ]);
            }

            $purchaseRequest->load(['materialRequest', 'items', 'items.product']);

            return Helpers::sendResponse(200, $purchaseRequest, 'Purchase request updated successfully');
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Purchase request not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error updating purchase request: ' . $e->getMessage());
        }
    }
    public function index(Request $request)
    {
        try {
            $purchaseRequests = PurchaseRequest::with(['status', 'materialRequest', 'items', 'items.product'])->get();
            return Helpers::sendResponse(200, $purchaseRequests, 'Purchase requests retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving purchase requests: ' . $e->getMessage());
        }
    }
}
