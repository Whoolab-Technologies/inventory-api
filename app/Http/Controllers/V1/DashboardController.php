<?php

namespace App\Http\Controllers\V1;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\V1\MaterialRequest;
use App\Models\V1\Product;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\StockTransfer;
use App\Models\V1\Store;
use Illuminate\Http\Request;
use App\Services\Helpers;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        try {
            $data = [];
            $productsCount = Product::count();
            $materialRequestCount = MaterialRequest::count();
            $recentMaterialRequests = MaterialRequest::with(['status', 'engineer', 'store'])->latest()->take(5)->get();
            $recentPurchaseRequest = PurchaseRequest::with(['status',])->latest()->take(5)->get();
            $stockTransfers = StockTransfer::with([
                'status',
                'materialRequest',
                'purchaseRequest',
                'materialReturn',
                'inventoryDispatch',
            ])->latest()->take(10)->get()
                ->map(function ($transfer) {
                    return [
                        'id' => $transfer->id,
                        'status_id' => $transfer->status_id,
                        'status' => $transfer->status?->name,
                        'request_type' => $transfer->request_type,
                        'request_number' => $transfer->request_number,
                        'request' => $transfer->request,
                        'created_at' => $transfer->created_at,
                    ];
                });
            $purchaseRequestCount = PurchaseRequest::count();
            $storeCount = Store::count();

            $data = [
                'material_request_count' => $materialRequestCount,
                'purchase_request_count' => $purchaseRequestCount,
                'recent_material_requests' => $recentMaterialRequests,
                'recent_purchase_requests' => $recentPurchaseRequest,
                'stock_tansfers' => $stockTransfers,
                'store_count' => $storeCount,
                'products_count' => $productsCount,
            ];
            return Helpers::sendResponse(200, $data, 'Data retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
