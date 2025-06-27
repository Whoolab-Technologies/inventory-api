<?php
namespace App\Services\V1;

use App\Models\V1\PurchaseRequest;
use App\Models\V1\PurchaseRequestItem;
use App\Models\V1\MaterialRequestItem;
use App\Data\PurchaseRequestData;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{

    public function createPurchaseRequest(PurchaseRequestData $data)
    {
        $purchaseRequest = PurchaseRequest::create([
            'purchase_request_number' => 'PR' . str_pad(PurchaseRequest::max('id') + 1001, 6, '0', STR_PAD_LEFT),
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
}