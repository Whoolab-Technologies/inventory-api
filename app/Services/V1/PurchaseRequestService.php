<?php
namespace App\Services\V1;

use App\Enums\StatusEnum;
use App\Models\V1\Lpo;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\MaterialRequestItem;
use App\Data\PurchaseRequestData;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class PurchaseRequestService
{

    public function createPurchaseRequest(PurchaseRequestData $data)
    {
        $purchaseRequest = PurchaseRequest::create([
            'purchase_request_number' => 'PR-' . date('Y') . '-' . str_pad(PurchaseRequest::max('id') + 1, 3, '0', STR_PAD_LEFT),

            'material_request_id' => $data->materialRequestId,
            'material_request_number' => $data->materialRequestNumber,
            'status_id' => $data->statusId,
        ]);

        $materialRequestItems = MaterialRequestItem::where('material_request_id', $data->materialRequestId)
            ->get()
            ->keyBy('product_id');

        foreach ($data->items as $item) {
            if (!isset($materialRequestItems[$item['product_id']])) {
                throw ValidationException::withMessages([
                    'material_request_item' => "Material request item not found for product ID {$item['product_id']}."
                ]);
            }

            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'material_request_item_id' => $materialRequestItems[$item['product_id']]->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['missing_quantity'],
            ]);
        }
        return $purchaseRequest;


    }



    public function createLpoWithItems(Request $request)
    {
        try {
            return \DB::transaction(function () use ($request) {
                $lpo = Lpo::create([
                    'lpo_number' => $request->lpo_number,
                    'pr_id' => $request->pr_id,
                    'supplier_id' => $request->supplier_id,
                    'date' => \Carbon\Carbon::parse($request->date ?? now())->format('Y-m-d'),
                    'status_id' => StatusEnum::APPROVED
                ]);
                $itemsRelation = $lpo->items();
                collect($request->items)
                    ->filter(fn($item) => $item['requested_quantity'] > 0)
                    ->each(function ($item) use ($lpo, $request, $itemsRelation) {
                        $itemsRelation->create([
                            'lpo_id' => $lpo->id,
                            'pr_id' => $request->pr_id,
                            'pr_item_id' => $item['pr_item_id'],
                            'requested_quantity' => $item['requested_quantity'],
                        ]);
                    });
                $lpo->load('items');
                return $lpo;
            });
        } catch (\Exception $e) {
            \Log::error('Failed to create LPO: ' . $e->getMessage());
            throw $e;
        }
    }

}