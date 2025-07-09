<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Stock;
use App\Services\Helpers;
use App\Models\V1\Product;
use App\Models\V1\Store;
use App\Models\V1\Engineer;
use App\Models\V1\StockMeta;
use App\Models\V1\StockTransaction;
use App\Models\V1\StockTransfer;
use App\Models\V1\StockTransferItem;
class StockController extends Controller
{
    public function index()
    {
        try {
            $stocks = Stock::with(['store', 'product.brand'])
                ->select('id', 'store_id', 'product_id', 'quantity')
                ->get()
                ->map(function ($stock) {
                    return $this->createStockData($stock);
                });
            return Helpers::sendResponse(200, $stocks, );

        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function show($id)
    {
        try {

            $stock = Stock::with(['store', 'product.brand', 'product.category'])->find($id);
            if ($stock) {
                $stock = $this->createStockData($stock);
            }
            $products = Product::all();
            $stores = Store::all();
            $engineers = Engineer::all();
            $response = [
                'stock' => $stock,
                'products' => $products,
                'engineers' => $engineers,
                'stores' => $stores
            ];
            return Helpers::sendResponse(200, $response);
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    public function store(Request $request)
    {
        \DB::beginTransaction();
        try {

            $this->validate($request, [
                'store_id' => 'required|integer',
                'product_id' => 'required|integer',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'dn_number' => 'required|string',
            ]);
            $quantityChange = $request->quantity;
            $movementType = $quantityChange > 0 ? 'IN' : 'OUT';

            // $stock = Stock::updateOrCreate(
            //     ['store_id' => $request->store_id, 'product_id' => $request->product_id],
            //     ['quantity' => \DB::raw("quantity + $quantityChange")]
            // );
            $store = Store::findOrFail($request->store_id);
            $stockMeta = new StockMeta();
            $stockMeta->store_id = $request->store_id;
            $stockMeta->product_id = $request->product_id;
            $stockMeta->quantity = $quantityChange;
            $stockMeta->supplier_id = $request->supplier_id;
            $stockMeta->lpo = $request->lpo;
            $stockMeta->dn_number = $request->dn_number;

            $stockMeta->save();
            $attributes = ['store_id' => $request->store_id, 'product_id' => $request->product_id];

            if (!$store->is_central_store) {
                $attributes['engineer_id'] = $request->engineer_id;
            }

            $stock = Stock::firstOrCreate(
                $attributes,
                ['quantity' => 0]
            );

            $stock->increment('quantity', $quantityChange);
            $stock = Stock::with(['store', 'product.brand', 'product.category'])->find($stock->id);
            StockTransaction::create([
                'store_id' => $request->store_id,
                'product_id' => $request->product_id,
                'engineer_id' => $request->engineer_id,
                'quantity' => abs($quantityChange),
                'stock_movement' => $movementType,
                'type' => "DIRECT",
                'lpo' => $request->lpo,
                'dn_number' => $request->dn_number,
            ]);

            $user = auth()->user();
            $isCentralStore = (Store::find($request->store_id))->is_central_store;
            $stockTransfers = new StockTransfer();
            $stockTransfers->transaction_number = 'TXN-' . str_pad(StockTransfer::max('id') + 1001, 6, '0', STR_PAD_LEFT);
            $stockTransfers->to_store_id = $request->store_id;
            $stockTransfers->from_store_id = $request->from_store_id;
            $stockTransfers->request_id = 0;
            $stockTransfers->received_by = $user->id;
            $stockTransfers->receiver_role = $isCentralStore ? "CENTRAL STORE" : "SITE STORE";
            $stockTransfers->request_type = "DIRECT";
            $stockTransfers->transaction_type = "DIRECT";
            $stockTransfers->dn_number = $request->dn_number;
            $stockTransfers->status_id = 7;
            $stockTransfers->save();


            $transferItem = new StockTransferItem();
            $transferItem->stock_transfer_id = $stockTransfers->id;
            $transferItem->product_id = $request->product_id;
            $transferItem->requested_quantity = 0;
            $transferItem->received_quantity = $transferItem->issued_quantity = $quantityChange;
            $transferItem->save();

            $stock = $this->createStockData($stock);

            \DB::commit();
            return Helpers::sendResponse(201, $stock);

        } catch (\Exception $e) {
            \DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stock = Stock::with(['store', 'product.brand', 'product.category'])->find($id);
            if ($stock) {
                $stock->update($request->all());
                $stock = $this->createStockData($stock);
                return Helpers::sendResponse(200, $stock);
            } else {
                return Helpers::sendResponse(404, [], 'Stock not found');
            }
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $stock = Stock::find($id);
            if ($stock) {
                $stock->delete();
                return Helpers::sendResponse(200, [], 'Stock deleted');
            } else {
                return Helpers::sendResponse(404, [], 'Stock not found');
            }
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    private function createStockData($stock)
    {
        return [
            'id' => $stock->id ?? null,
            'store_id' => $stock->store_id ?? null,
            'brand' => $stock->product->brand ?? null,
            'category' => $stock->product->category ?? null,
            'cat_id' => $stock->product->cat_id ?? null,
            'store_name' => $stock->store?->name ?? null,
            'product_id' => $stock->product_id ?? null,
            'product_name' => $stock?->product->item ?? null,
            'quantity' => $stock->quantity ?? null,
            'unit' => $stock->product?->unit?->id ?? null,
            'symbol' => $stock->product->unit->symbol ?? null,
            'transactions' => $stock->transactions,
        ];
    }

    public function getTransactions($id)
    {
        try {
            $stock = Stock::find($id);
            if ($stock) {
                $transactions = $stock->transactions()->with(['product', 'store', 'engineer.store'])
                    ->orderBy('id', 'desc')->get()
                    ->map(function ($transaction) {
                        return [
                            "id" => $transaction->id,
                            "store_id" => $transaction->store->id ?? null,
                            "store_name" => $transaction->store->name ?? null,
                            "product_name" => $transaction->product->item ?? null,
                            "quantity" => $transaction->quantity,
                            "stock_movement" => $transaction->stock_movement,
                            "engineer_id" => $transaction->engineer->id ?? null,
                            "engineer_name" => $transaction->engineer ? $transaction->engineer->name : null,
                            "engineer_store_id" => $transaction->engineer->store->id ?? null,
                            "engineer_store_name" => $transaction->engineer->store->name ?? null,
                            "transfer_date" => $transaction->created_at,
                        ];
                    });
                return Helpers::sendResponse(200, $transactions, "Retrieved all transactions");

            } else {
                return Helpers::sendResponse(404, [], 'Stock not found');
            }
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());

        }
    }
}
