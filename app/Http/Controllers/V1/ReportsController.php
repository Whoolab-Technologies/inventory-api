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
            $fromDate = $request->query('from_date', Carbon::now()->startOfYear()->format('Y-m-d'));
            $toDate = $request->query('to_date', Carbon::now()->format('Y-m-d'));
            $storeId = $request->query('store_id');
            $productId = $request->query('product_id');

            $transactions = StockTransaction::with(['product', 'store', 'engineer.store'])
                ->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $toDate);

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
                    'from_store' => $tx->store->name ?? 'N/A',
                    'to_store' => $tx->engineer->store->name ?? 'N/A',
                    'quantity' => $tx->quantity,
                    'consumption' => $tx->store->id == $tx->engineer->store->id,
                    'transaction_type' => strtoupper($tx->stock_movement),
                    'date_of_transaction' => $tx->created_at->format('Y-m-d'),
                ];
            });
            return Helpers::sendResponse(200, $transactions, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }


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
