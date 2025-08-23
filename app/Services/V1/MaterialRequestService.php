<?php

namespace App\Services\V1;

use App\Enums\StatusEnum;
use App\Models\V1\MaterialRequest;
use App\Models\V1\MaterialRequestFile;
use App\Data\PurchaseRequestData;
use Illuminate\Http\Request;
use App\Services\Helpers;
class MaterialRequestService
{

    protected $purchaseRequestService;
    public function __construct(
        PurchaseRequestService $purchaseRequestService,
    ) {

        $this->purchaseRequestService = $purchaseRequestService;
    }

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
            $materialRequest->description = $request->description;
            if ($request->status_id == StatusEnum::APPROVED->value) {
                $materialRequest->approved_date = \Carbon\Carbon::now();
            }
            $materialRequest->save();
            if ($request->create_pr) {
                $missingItems = [];
                foreach ($request->items as $item) {
                    $requestedQty = (int) $item['requested_quantity'];
                    $productId = (int) $item['product_id'];
                    $missingItems[] = [
                        'product_id' => $productId,
                        'missing_quantity' => $requestedQty
                    ];
                }

                if (count($missingItems)) {
                    $this->purchaseRequestService->createPurchaseRequest(new PurchaseRequestData(
                        $materialRequest->id,
                        $materialRequest->request_number,
                        items: $missingItems
                    ));
                }
            }
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


    public function uploadMaterialRequestImages(Request $request, $materialRequest)
    {
        $files = $request->file('files')
            ?? [];

        if (empty($files) || !is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            $filePath = Helpers::uploadFile($file, "images/material-request/{$materialRequest->id}");

            MaterialRequestFile::create([
                'file' => $filePath,
                'file_mime_type' => $mimeType,
                'material_request_id' => $materialRequest->id,
                'transaction_type' => "create",
            ]);
        }
    }
}
