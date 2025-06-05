<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\StockTransaction;
use Illuminate\Support\Carbon;
use App\Exports\MaterialInOutTransactionExport;
use Maatwebsite\Excel\Facades\Excel;
class ReportsController extends Controller
{

    public function transactionReport(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $date = $request->query('date', Carbon::now()->format('Y-m-d'));
            $storeId = $request->query('store');
            $productId = $request->query('product');

            $transactions = StockTransaction::with(['product', 'store'])
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

            $transactions = $transactions->orderByDesc('id')->get()->map(function ($tx) {
                return [
                    'material_name' => $tx->product->item ?? 'N/A',
                    'store' => $tx->store->name ?? 'N/A',
                    'quantity' => $tx->quantity,
                    'consumption' => $tx->type === "CONSUMPTION",
                    'transaction_type' => strtoupper($tx->stock_movement),
                    'type' => strtoupper($tx->type),
                    'date_of_transaction' => $tx->created_at->format('Y-m-d'),
                ];
            });

            return Helpers::sendResponse(200, $transactions, 'Transactions retrieved successfully');

        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }

    }

    //  public function transactionReport(Request $request)
//     {
//         try {

    //             $searchTerm = $request->query('search');
//             $fromDate = $request->query('from_date', Carbon::now()->startOfYear()->format('Y-m-d'));
//             $toDate = $request->query('to_date', Carbon::now()->format('Y-m-d'));
//             $storeId = $request->query('store_id');
//             $productId = $request->query('product_id');

    //             $transactions = StockTransaction::with(['product', 'store', 'engineer.store'])
//                 ->whereDate('created_at', '>=', $fromDate)
//                 ->whereDate('created_at', '<=', $toDate);

    //             if ($searchTerm) {
//                 $transactions->search($searchTerm);
//             }

    //             if ($storeId) {
//                 $transactions->where('store_id', $storeId);
//             }

    //             if ($productId) {
//                 $transactions->where('product_id', $productId);
//             }

    //             $grouped = $transactions->get()
//                 ->groupBy(fn($tx) => $tx->product_id . '-' . $tx->store_id)
//                 ->map(function ($group) {
//                     $first = $group->first();
//                     $increased = $group->where('stock_movement', 'INCREASED')->sum('quantity');
//                     $decreased = $group->where('stock_movement', 'DECREASED')->sum('quantity');

    //                     return [
//                         'materialName' => $first->product->item ?? 'N/A',
//                         'storeName' => $first->store->name ?? 'N/A',
//                         'totalIncreased' => $increased,
//                         'totalDecreased' => $decreased,
//                         'storeId' => $first->store_id,
//                         'productId' => $first->product_id,
//                     ];
//                 })->values();

    //             return Helpers::sendResponse(200, $grouped, 'Transactions retrieved successfully');
//         } catch (\Exception $e) {
//             return Helpers::sendResponse(500, [], $e->getMessage());
//         }
//     }
    public function exportTransactions(Request $request)
    {
        try {
            $fromDate = $request->query('from_date', Carbon::now()->startOfYear()->format('Y-m-d'));
            $toDate = $request->query('to_date', Carbon::now()->format('Y-m-d'));
            $storeId = $request->query('store_id');
            $productId = $request->query('product_id');
            $searchTerm = $request->query('search');
            $fileName = 'exports/stock_transactions.xlsx';
            Excel::store(
                new MaterialInOutTransactionExport($fromDate, $toDate, $storeId, $productId, $searchTerm),
                $fileName,
                'public'
            );
            $filePath = storage_path('app/' . $fileName);
            return Helpers::download($filePath, false);
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

}
