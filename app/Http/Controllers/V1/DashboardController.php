<?php

namespace App\Http\Controllers\V1;

use App\Enums\StatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\V1\MaterialRequest;
use App\Models\V1\Product;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\StockTransfer;
use App\Models\V1\Store;
use App\Models\V1\MaterialRequestItem;
use App\Models\V1\Lpo;
use App\Services\Helpers;
use App\Services\V1\DashboardService;

class DashboardController extends Controller
{

    protected $dashboardService;
    public function __construct(
        DashboardService $dashboardService,
    ) {
        $this->dashboardService = $dashboardService;
    }
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
            $topProducts = MaterialRequestItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT material_request_id) as total_mrs'),
                DB::raw('COUNT(*) as total_added')
            )
                ->with('product')
                ->groupBy('product_id')
                ->orderByDesc('total_added')
                ->limit(5)
                ->get();

            $topSuppliers = Lpo::select(
                'supplier_id',
                DB::raw('COUNT(*) as total_lpos')
            )
                ->with('supplier')
                ->where('status_id', StatusEnum::COMPLETED->value)
                ->groupBy('supplier_id')
                ->orderByDesc('total_lpos')
                ->limit(5)
                ->get();

            $mrPrLpoTrends = $this->dashboardService->getMrPrLpoTrends();
            $mrByStores = $this->dashboardService->getMrByStores();
            $mrTimeLine = $this->dashboardService->getMrTimeline();
            $lowStockProducts = $this->dashboardService->getLowStockProducts();

            $data = [
                'material_request_count' => $materialRequestCount,
                'purchase_request_count' => $purchaseRequestCount,
                'recent_material_requests' => $recentMaterialRequests,
                'recent_purchase_requests' => $recentPurchaseRequest,
                'stock_tansfers' => $stockTransfers,
                'store_count' => $storeCount,
                'products_count' => $productsCount,
                'top_products' => $topProducts,
                'top_suppliers' => $topSuppliers,
                'mr_pr_lpo_trends ' => $mrPrLpoTrends,
                'mr_by_stores' => $mrByStores,
                'mr_time_line' => $mrTimeLine,
                'low_stock_products' => $lowStockProducts,
            ];

            return Helpers::sendResponse(200, $data, 'Data retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
