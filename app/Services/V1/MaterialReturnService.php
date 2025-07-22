<?php
namespace App\Services\V1;

use App\Data\StockTransactionData;
use App\Enums\RequestType;
use App\Enums\StatusEnum;
use App\Enums\StockMovement;
use App\Enums\StockMovementType;
use App\Enums\TransactionType;
use App\Enums\TransferPartyRole;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnFile;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\StockInTransit;
use App\Models\V1\Stock;
use App\Models\V1\StockTransaction;
use App\Models\V1\StockTransfer;
use App\Models\V1\Store;
use App\Data\StockTransferData;
use App\Data\StockInTransitData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\Helpers;
class MaterialReturnService
{
    protected $stockTransferService;
    public function __construct(
        StockTransferService $stockTransferService,
    ) {
        $this->stockTransferService = $stockTransferService;
    }

    public function createMaterialReturns(Request $request)
    {
        \DB::beginTransaction();
        try {

            $engineerId = $request->engineer_id ?? null;
            $dnNumber = $request->dn_number ?? null;
            $products = $request->products ?? [];
            if (empty($engineerId)) {
                throw ValidationException::withMessages([
                    "engineer_id" => "Engineer ID is required."
                ]);
            }
            if (empty($dnNumber)) {
                throw ValidationException::withMessages([
                    "dn_number" => "DN Number is required."
                ]);
            }
            if (empty($products) || !is_array($products)) {
                throw ValidationException::withMessages([
                    "products" => "Products are required and must be an array."
                ]);
            }
            foreach ($products as $index => $product) {
                if (
                    !isset($product['product_id']) ||
                    !isset($product['issued']) ||
                    !is_numeric($product['issued']) ||
                    $product['issued'] <= 0
                ) {
                    throw ValidationException::withMessages([
                        "products.$index" => "Each product must have a valid product_id and issued quantity greater than 0."
                    ]);
                }
            }


            $materialReturn = new MaterialReturn();
            $materialReturn->return_number = 'IR-' . date('Y') . '-' . str_pad(MaterialReturn::max('id') + 1, 3, '0', STR_PAD_LEFT);
            $materialReturn->from_store_id = $request->from_store_id;
            $materialReturn->to_store_id = $request->to_store_id;
            $materialReturn->dn_number = $dnNumber;
            $materialReturn->status_id = StatusEnum::IN_TRANSIT->value;
            $materialReturn->save();


            $materialReturnDetail = new MaterialReturnDetail();
            $materialReturnDetail->material_return_id = $materialReturn->id;
            $materialReturnDetail->engineer_id = $engineerId;
            $materialReturnDetail->save();


            foreach ($products as $product) {
                $materialReturnItem = new MaterialReturnItem();
                $materialReturnItem->material_return_id = $materialReturn->id;
                $materialReturnItem->material_return_detail_id = $materialReturnDetail->id;
                $materialReturnItem->product_id = $product['product_id'];
                $materialReturnItem->issued = $product['issued'];
                $materialReturnItem->save();
            }
            $this->uploadMaterialReturnImages($request, $materialReturn, 'transfer');

            $this->createStockTransferWithItems(
                $request->from_store_id,
                $request->to_store_id,
                $materialReturn->id,
                $products,
                $dnNumber,
                $engineerId
            );

            \DB::commit();

            $materialReturn->load([
                'status',
                'fromStore',
                'toStore',
                'details.engineer',
                'details.items.product',
            ]);
            return $materialReturn;
        } catch (\Throwable $th) {
            \DB::rollBack();
            throw $th;
        }
    }


    private function createStockTransferWithItems(
        $fromStoreId,
        $toStoreId,
        $requestId,
        array $items,
        $dnNumber,
        $engneerId
    ) {
        $stockTransferData = new StockTransferData(
            $fromStoreId,
            $toStoreId,
            StatusEnum::IN_TRANSIT,
            $dnNumber,
            null,
            $requestId,
            RequestType::SS_RETURN,
            TransactionType::SS_CS,
            auth()->user()->id,
            TransferPartyRole::SITE_STORE,

        );
        $transfer = $this->stockTransferService->createStockTransfer($stockTransferData);

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $issued = $item['issued'];
            $transferItem = $this->stockTransferService->createStockTransferItem(
                $transfer->id,
                $productId,
                $issued,
                $issued
            );
            $this->stockTransferService->updateStock($fromStoreId, $productId, -abs($issued), $engneerId);

            $materialReturnItem = MaterialReturnItem::where('material_return_id', $requestId)
                ->where('product_id', $productId)
                ->firstOrFail();
            $stockInTransitData = new StockInTransitData(
                stockTransferId: $transfer->id,
                stockTransferItemId: $transferItem->id,
                productId: $productId,
                issuedQuantity: $issued,
                materialRequestId: null,
                materialRequestItemId: null,
                materialReturnId: $requestId,
                materialReturnItemId: $materialReturnItem->id,

            );
            $this->stockTransferService->createStockInTransit($stockInTransitData);


            $stockTransactionData = new StockTransactionData(
                $fromStoreId,
                $productId,
                $engneerId,
                $issued,
                StockMovementType::SS_RETURN,
                StockMovement::TRANSIT,
                null,
                $dnNumber,
            );
            $this->stockTransferService->createStockTransaction($stockTransactionData);

        }
    }

    public function updateMaterialReturns($id, $request)
    {
        \DB::beginTransaction();
        try {
            $materialReturn = MaterialReturn::where('id', $id)->first();
            $materialReturn = $this->updateStock($request, $materialReturn);
            \DB::commit();
            return $materialReturn->load([
                'fromStore',
                'status',
                'toStore',
                'details.engineer',
                'details.items.product',
            ]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private function updateStock(Request $request, MaterialReturn $materialReturn)
    {
        \DB::beginTransaction();
        try {

            $fromStoreId = $materialReturn->from_store_id;
            $toStoreId = $materialReturn->to_store_id;
            $isPartiallyReceived = false;

            $engineerId = $request->engineer_id;
            $products = $request->items;
            $productIds = collect($products)->pluck('product_id')->unique();
            $stockTransfer = StockTransfer::where('request_id', $materialReturn->id)
                ->where('request_type', RequestType::SS_RETURN->value)->firstOrFail();
            $stockInTransitRecords = StockInTransit::whereIn('product_id', $productIds)
                ->whereIn('material_return_item_id', collect($products)->pluck('id'))
                ->get()
                ->keyBy('product_id');
            $user = Auth::user();
            $tokenName = optional($user?->currentAccessToken())->name;
            foreach ($products as $item) {
                $productId = $item['product_id'];
                $receivedQuantity = $item['received'];

                foreach ($stockTransfer->items as $transferItem) {
                    if ($transferItem->product_id == $productId) {
                        $transferItem->received_quantity = $receivedQuantity;
                        $transferItem->save();
                        break;
                    }
                }
                $stockInTransit = $stockInTransitRecords[$productId] ?? null;
                if (!$stockInTransit || $stockInTransit->received_quantity > 0) {
                    continue;
                }
                $remaining = max(0, $stockInTransit->issued_quantity - $receivedQuantity);
                // Update stock in transit
                $stockInTransit->update([
                    'received_quantity' => $receivedQuantity,
                    'status_id' => $remaining > 0 ? StatusEnum::PARTIALLY_RECEIVED->value : StatusEnum::RECEIVED->value,
                ]);
                // Update material return item
                MaterialReturnItem::where('id', $item['id'])->update([
                    'product_id' => $productId,
                    'received' => $receivedQuantity,
                ]);
                // Update to store stock
                $this->stockTransferService->updateStock($toStoreId, $productId, $receivedQuantity);
                // Restore remaining quantity to engineer stock
                if ($remaining > 0) {
                    $isPartiallyReceived = true;
                    $this->stockTransferService->updateStock($fromStoreId, $productId, $remaining, $engineerId);
                }

                // Delete previous transit transactions
                StockTransaction::where('store_id', $fromStoreId)
                    ->where('product_id', $productId)
                    ->where('engineer_id', $engineerId)
                    ->where('stock_movement', StockMovement::TRANSIT)
                    ->where('type', StockMovementType::SS_RETURN)
                    ->delete();
                // Log new stock transactions
                if ($receivedQuantity > 0) {
                    StockTransaction::insert([
                        [
                            'store_id' => $fromStoreId,
                            'product_id' => $productId,
                            'engineer_id' => $engineerId,
                            'quantity' => $receivedQuantity,
                            'stock_movement' => StockMovement::OUT,
                            'type' => StockMovementType::SS_RETURN,
                            'dn_number' => $materialReturn->dn_number ?? null,
                            'created_by' => $user->id ?? null,
                            "created_type" => $tokenName,
                            "updated_by" => $user->id ?? null,
                            'updated_type' => $tokenName,
                            'created_at' => now(),
                            'updated_at' => now()
                        ],
                        [
                            'store_id' => $toStoreId,
                            'product_id' => $productId,
                            'engineer_id' => $engineerId,
                            'quantity' => $receivedQuantity,
                            'stock_movement' => StockMovement::IN,
                            'type' => StockMovementType::SS_RETURN,
                            'dn_number' => $materialReturn->dn_number ?? null,
                            'created_by' => $user->id ?? null,
                            "created_type" => $tokenName,
                            "updated_by" => $user->id ?? null,
                            'updated_type' => $tokenName,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    ]);
                }
            }

            $stockTransfer->status_id = StatusEnum::COMPLETED->value;
            $stockTransfer->received_by = $user->id;
            $stockTransfer->receiver_role = TransferPartyRole::CENTRAL_STORE;
            $stockTransfer->save();

            $materialReturn->status_id = $isPartiallyReceived
                ? StatusEnum::PARTIALLY_RECEIVED->value
                : StatusEnum::RECEIVED->value;
            $materialReturn->save();
            \DB::commit();
            return $materialReturn;

        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }


    protected function appendEngineerId($attribute, $storeId, $engineerId)
    {
        if (optional(Store::find($storeId))->is_central_store === false) {
            $attribute['engineer_id'] = $engineerId;
        }
        return $attribute;
    }
    public function uploadMaterialReturnImages(Request $request, $materialReturn, $transactionType = "receive")
    {
        $files = $request->file('files')
            ?? [];

        if (empty($files) || !is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            $filePath = Helpers::uploadFile($file, "images/material-return/{$materialReturn->id}");

            MaterialReturnFile::create([
                'file' => $filePath,
                'file_mime_type' => $mimeType,
                'material_return_id' => $materialReturn->id,
                'transaction_type' => $transactionType,
            ]);
        }
    }
}