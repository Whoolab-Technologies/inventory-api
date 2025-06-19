<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\PurchaseRequest;
use App\Services\Helpers;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function update(Request $request, $id)
    {
        try {
            $purchaseRequest = PurchaseRequest::findOrFail($id);

            // Validate input (adjust rules as needed)
            $validated = $request->validate([
                // Example fields:
                'status' => 'sometimes|string',
                'description' => 'sometimes|string|nullable',
                // Add other fields as necessary
            ]);

            // Update fields
            $purchaseRequest->fill($validated);
            $purchaseRequest->save();

            // Reload relationships if needed
            $purchaseRequest->load(['materialRequest', 'items']);

            return Helpers::sendResponse(200, $purchaseRequest, 'Purchase request updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return Helpers::sendResponse(422, null, 'Validation error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error updating purchase request: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        try {
            $purchaseRequests = PurchaseRequest::with(['materialRequest', 'items'])->get();
            return Helpers::sendResponse(200, $purchaseRequests, 'Purchase requests retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, null, 'Error retrieving purchase requests: ' . $e->getMessage());
        }
    }
}
