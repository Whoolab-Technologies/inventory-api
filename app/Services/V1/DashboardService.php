<?php
namespace App\Services\V1;

use App\Enums\StatusEnum;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\V1\MaterialRequest;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\Lpo;
use App\Models\V1\ProductMinStock;

class DashboardService
{
    public function __construct()
    {

    }
    public function getMrPrLpoTrends()
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // MRs grouped by month
        $mrs = MaterialRequest::select(
            DB::raw('DATE_FORMAT(created_at, "%b %Y") as month'),
            DB::raw('COUNT(*) as total'),
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as ym')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('ym', 'month')
            ->orderBy('ym')
            ->pluck('total', 'month');

        // PRs grouped by month
        $prs = PurchaseRequest::select(
            DB::raw('DATE_FORMAT(created_at, "%b %Y") as month'),
            DB::raw('COUNT(*) as total'),
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as ym')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('ym', 'month')
            ->orderBy('ym')
            ->pluck('total', 'month');

        // LPOs (completed only) grouped by month
        $lpos = Lpo::select(
            DB::raw('DATE_FORMAT(created_at, "%b %Y") as month'),
            DB::raw('COUNT(*) as total'),
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as ym')
        )
            ->where('status_id', 7)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('ym', 'month')
            ->orderBy('ym')
            ->pluck('total', 'month');

        // Build last 12 months list in "Mon YYYY" format
        $months = collect(range(0, 11))
            ->map(fn($i) => Carbon::now()->subMonths(11 - $i)->format('M Y'));

        return [
            'categories' => $months,
            'mrs' => $months->map(fn($m) => $mrs[$m] ?? 0)->values(),
            'prs' => $months->map(fn($m) => $prs[$m] ?? 0)->values(),
            'lpos' => $months->map(fn($m) => $lpos[$m] ?? 0)->values(),
        ];
    }

    public function getMrByStores()
    {
        $data = MaterialRequest::select(
            'store_id',
            DB::raw('COUNT(*) as total')
        )
            ->groupBy('store_id')
            ->with('store')
            ->get();

        return [
            'labels' => $data->map(fn($row) => $row->store->name ?? "Store {$row->store_id}"),
            'series' => $data->pluck('total')
        ];
    }


    public function getMrTimeline()
    {
        return MaterialRequest::select('id', 'request_number', 'created_at', 'updated_at')
            ->where("status_id", StatusEnum::COMPLETED->value)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($mr) {
                return [
                    'x' => $mr->request_number,
                    'y' => [
                        strtotime($mr->created_at) * 1000,
                        strtotime($mr->updated_at) * 1000
                    ]
                ];
            });
    }

    public function getLowStockProducts()
    {
        $lowStockProducts = ProductMinStock::select(
            'product_min_stocks.*',
            \DB::raw('SUM(stock.quantity) as total_stock')
        )
            ->join('stock', function ($join) {
                $join->on('stock.product_id', '=', 'product_min_stocks.product_id')
                    ->on('stock.store_id', '=', 'product_min_stocks.store_id');
            })
            ->groupBy('product_min_stocks.id', 'product_min_stocks.product_id', 'product_min_stocks.store_id', 'product_min_stocks.min_stock_qty')
            ->havingRaw('SUM(stock.quantity) < product_min_stocks.min_stock_qty')
            ->with(['product', 'store'])
            ->get();
        return $lowStockProducts;
    }
}