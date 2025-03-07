<?php

namespace App\Services\V1;

use App\Models\V1\MaterialRequest;
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
        try {
            $materialRequest = MaterialRequest::findOrFail($id);
            \Log::info("request  status " . $request->status);
            $materialRequest->status = $request->status;

            \Log::info("materialRequest  status " . $materialRequest->status);

            if ($request->status == 'completed') {
                // validate the item count
                //create transaction with transaction items
            }
            $materialRequest->save();
            return $materialRequest;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
