<?php

namespace App\Services\V1;

use App\Models\V1\MaterialRequest;
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
            \Log::info("User {$user->id} is updating Material Request ID: {$id}");

            $materialRequest = MaterialRequest::findOrFail($id);
            $materialRequest->status_id = $request->status_id;
            $materialRequest->save();

            \Log::info("Material Request ID: {$materialRequest->id} status updated to {$request->status_id}");

            if ($request->create_pr) {
                \Log::info("Create Purchase Request flag is set. Preparing missing items for Material Request ID: {$materialRequest->id}");

                $missingItems = [];
                foreach ($request->items as $item) {
                    $requestedQty = (int) $item['requested_quantity'];
                    $productId = (int) $item['product_id'];
                    $missingItems[] = [
                        'product_id' => $productId,
                        'missing_quantity' => $requestedQty
                    ];
                    \Log::info("Missing item added: Product ID {$productId}, Missing Quantity {$requestedQty}");
                }

                if (count($missingItems)) {
                    \Log::info("Creating Purchase Request for Material Request ID: {$materialRequest->id} with " . count($missingItems) . " items");
                    $this->purchaseRequestService->createPurchaseRequest(new PurchaseRequestData(
                        $materialRequest->id,
                        $materialRequest->request_number,
                        items: $missingItems
                    ));
                }
            }

            \DB::commit();
            \Log::info("Material Request ID: {$materialRequest->id} update process completed successfully");

            return $materialRequest;
        } catch (\Throwable $th) {
            \DB::rollBack();
            \Log::error("Error updating Material Request ID: {$id} - " . $th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);
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
