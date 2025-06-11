<?php

namespace App\Http\Controllers\V1;

use App\Exports\GenericExcelExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\StockTransaction;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\StockMeta;
use Illuminate\Support\Carbon;
use App\Exports\MaterialConsumptionExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
class ReportsController extends Controller
{

    public function transactionReport(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $date = $request->query('date', Carbon::now()->format('Y-m-d'));
            $storeId = $request->query('store');
            $productId = $request->query('product');


            $transactions = StockTransaction::with(['product', 'store', 'engineer'])
                ->whereBetween('created_at', [
                    Carbon::parse($date)->startOfDay(),
                    Carbon::parse($date)->endOfDay(),
                ]);

            if ($searchTerm) {
                $transactions->search($searchTerm);
            }

            if ($storeId) {
                $transactions->where('store_id', $storeId);
            }

            if ($productId) {
                $transactions->where('product_id', $productId);
            }

            $transactions = $transactions->orderByDesc('id')->get()
                ->map(function ($tx, $index) {
                    $meta = 'N/A';

                    // Only try to get stock_meta if type is STOCK
                    if (strtoupper($tx->type) === "STOCK") {
                        $meta = StockMeta::where('dn_number', $tx->dn_number)
                            ->where('store_id', $tx->store_id)
                            ->where('product_id', $tx->product_id)
                            ->first();
                    }

                    return [
                        'id' => $index + 1,
                        'material_name' => $tx->product->item ?? 'N/A',
                        'store' => $tx->store->name ?? 'N/A',
                        'quantity' => $tx->quantity,
                        'consumption' => $tx->type === "CONSUMPTION",
                        'transaction_type' => strtoupper($tx->stock_movement),
                        'type' => strtoupper($tx->type),
                        'date_of_transaction' => $tx->created_at->format('Y-m-d'),
                        'dn_number' => $tx->dn_number ?? 'N/A',
                        'lpo' => $tx->lpo ?? 'N/A',
                        'engineer' => $tx->engineer->name ?? 'N/A',
                        'meta' => $meta
                    ];
                });

            return Helpers::sendResponse(200, $transactions, 'Transactions retrieved successfully');

        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }

    }



    public function materialReturnReport(Request $request)
    {
        try {
            $date = $request->query('date', Carbon::now()->format('Y-m-d'));
            $storeId = $request->query('store');
            $productId = $request->query('product');

            // $returnItems = MaterialReturn::with(['items', 'fromStore'])
            //     ->whereBetween('created_at', [
            //         Carbon::parse($date)->startOfDay(),
            //         Carbon::parse($date)->endOfDay(),
            //     ])
            //     ->when($storeId, function ($query, $storeId) {
            //         $query->whereHas('fromStore', function ($q) use ($storeId) {
            //             $q->where('id', $storeId);
            //         });
            //     })
            //     ->when($productId, function ($query, $productId) {
            //         $query->whereHas('items', function ($q) use ($productId) {
            //             $q->where('product_id', $productId);
            //         });
            //     })
            //     ->orderByDesc('id')
            //     ->get()
            //     ->flatMap(function ($item) use ($productId) {
            //         $filteredItems = $productId
            //             ? $item->items->where('product_id', $productId)
            //             : $item->items;

            //         return $filteredItems->map(function ($productItem) use ($item) {
            //             return [
            //                 'product_id' => $productItem->product_id,
            //                 'issued_quantity' => $productItem->issued,
            //                 'received_quantity' => $productItem->received,
            //                 'return_date' => $item->created_at?->format('Y-m-d H:i:s'),
            //                 'site_of_origin' => $item->fromStore->name ?? 'N/A',
            //             ];
            //         });
            //     })
            //     ->values();

            $returnItems = MaterialReturnItem::with(['materialReturn.fromStore'])
                ->whereHas('materialReturn', function ($query) use ($date, $storeId) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($date)->startOfDay(),
                        Carbon::parse($date)->endOfDay(),
                    ]);
                    if ($storeId) {
                        $query->where('from_store_id', $storeId);  // or whatever foreign key you use
                    }
                })
                ->when($productId, function ($query, $productId) {
                    $query->where('product_id', $productId);
                })
                ->orderByDesc('id')
                ->get()
                ->map(function ($item, $index) {
                    return [
                        'id' => $index + 1,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->item,
                        'quantity' => $item->issued,
                        'received_quantity' => $item->received,
                        'return_date' => $item->materialReturn->created_at?->format('Y-m-d'),
                        'site_of_origin' => $item->materialReturn->fromStore->name ?? 'N/A',
                    ];
                });

            return Helpers::sendResponse(200, $returnItems, 'Material return transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function summaryReport(Request $request)
    {
        try {

            $storeId = $request->query('store');
            $productId = $request->query('product');

            $transactions = StockTransaction::with(['product', 'store', 'engineer.store']);

            if ($storeId) {
                $transactions->where('store_id', $storeId);
            }

            if ($productId) {
                $transactions->where('product_id', $productId);
            }
            // $data = $transactions->get();
            $grouped = $transactions->get()
                ->groupBy(fn($tx) => $tx->store_id . '-' . $tx->product_id . '-' . $tx->created_at->format('Y-m-d'))
                ->map(function ($group, $key) {
                    $first = $group->first();
                    [$storeId, $productId, $date] = explode('-', $key);

                    return [
                        'id' => $key,
                        'storeId' => (int) $storeId,
                        'storeName' => $first->store->name ?? 'N/A',
                        'productId' => (int) $productId,
                        'materialName' => $first->product->item ?? 'N/A',
                        'materialId' => $first->product->cat_id ?? 'N/A',
                        'brand' => $first->product->brand->name ?? 'N/A',
                        'category' => $first->product->category->name ?? 'N/A',
                        'totalIncreased' => $group->where('stock_movement', 'IN')->sum('quantity'),
                        'totalDecreased' => $group->where('stock_movement', 'OUT')->sum('quantity'),
                        'date' => optional($first->created_at)->format('Y-m-d'), // or use now() if date missing
                    ];
                })
                ->values();


            return Helpers::sendResponse(200, $grouped, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    public function consumptionReport(Request $request)
    {
        try {

            $storeId = $request->query('store');
            $productId = $request->query('product');
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(6)->startOfDay();
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

            $transactions = StockTransaction::with(['product', 'store', 'engineer.store'])
                ->whereBetween('created_at', [$start, $end]);

            if ($storeId) {
                $transactions->where('store_id', $storeId);
            }

            if ($productId) {
                $transactions->where('product_id', $productId);
            }

            $grouped = $transactions->get()
                ->groupBy(fn($tx) => $tx->store_id . '-' . $tx->product_id . '-' . $tx->created_at->format('Y-m-d'))
                ->map(function ($group, $key) {
                    $first = $group->first();
                    [$storeId, $productId, $date] = explode('-', $key);

                    return [
                        'id' => $key,
                        'storeId' => (int) $storeId,
                        'storeName' => $first->store->name ?? 'N/A',
                        'productId' => (int) $productId,
                        'materialName' => $first->product->item ?? 'N/A',
                        'materialId' => $first->product->cat_id ?? 'N/A',
                        'brand' => $first->product->brand->name ?? 'N/A',
                        'category' => $first->product->category->name ?? 'N/A',
                        'totalIncreased' => $group->where('stock_movement', 'IN')->sum('quantity'),
                        'totalDecreased' => $group->where('stock_movement', 'OUT')->sum('quantity'),
                        'date' => optional($first->created_at)->format('Y-m-d'), // or use now() if date missing
                    ];
                })
                ->values();

            //return Helpers::sendResponse(200, ['grouped' => $grouped, 'start' => $start, 'end' => $end], 'Transactions retrieved successfully');

            return Helpers::sendResponse(200, $grouped, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function exportReport(Request $request)
    {
        try {
            $reports = $request->input('reports', []);
            $fileName = 'material_consumption_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = "export/$fileName";
            Excel::store(new MaterialConsumptionExport($reports), $relativePath, 'public');
            return Helpers::sendResponse(200, URL::to(Storage::url($relativePath)), 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    public function genericExcelExport(Request $request)
    {
        try {
            $data = $request->input('data', []);
            $headers = $request->input('headers', []);
            $title = $request->input('title', "SUMMARY REPORT");
            $fileName = $title . "_" . now()->format('Ymd_His') . '.xlsx';
            $relativePath = "export/$fileName";
            // $filePath = storage_path('app/public/' . $relativePath);
            Excel::store(new GenericExcelExport($data, $headers, $title), $relativePath, 'public');
            //return Helpers::download($filePath);
            return Helpers::sendResponse(200, URL::to(Storage::url($relativePath)), 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
