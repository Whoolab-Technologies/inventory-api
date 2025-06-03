<?php

namespace App\Exports;
use App\Models\V1\StockTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MaterialInOutTransactionExport implements FromCollection, WithHeadings
{
    protected $fromDate, $toDate, $storeId, $productId, $searchTerm;

    public function __construct($fromDate, $toDate, $storeId = null, $productId = null, $searchTerm = null)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->storeId = $storeId;
        $this->productId = $productId;
        $this->searchTerm = $searchTerm;
    }

    public function collection()
    {
        $transactions = StockTransaction::with(['product', 'store', 'engineer.store'])
            ->whereDate('created_at', '>=', $this->fromDate)
            ->whereDate('created_at', '<=', $this->toDate);

        if ($this->searchTerm) {
            $transactions->search($this->searchTerm);
        }

        if ($this->storeId) {
            $transactions->where('store_id', $this->storeId);
        }

        if ($this->productId) {
            $transactions->where('product_id', $this->productId);
        }

        return $transactions->get()->map(function ($tx) {
            return [
                $tx->product->item ?? 'N/A',
                $tx->quantity,
                strtoupper($tx->stock_movement),
                $tx->created_at->format('Y-m-d'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Material Name',
            'Quantity',
            'Transaction Type',
            'Date of Transaction',
        ];
    }
}