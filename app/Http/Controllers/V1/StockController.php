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
        try {
            \DB::beginTransaction();
            $quantityChange = $request->quantity;
            $movementType = $quantityChange > 0 ? 'IN' : 'OUT';

            // $stock = Stock::updateOrCreate(
            //     ['store_id' => $request->store_id, 'product_id' => $request->product_id],
            //     ['quantity' => \DB::raw("quantity + $quantityChange")]
            // );
            $stockMeta = new StockMeta();
            $stockMeta->store_id = $request->store_id;
            $stockMeta->product_id = $request->product_id;
            $stockMeta->quantity = $quantityChange;
            $stockMeta->supplier = $request->supplier;
            $stockMeta->lpo = $request->lpo;
            $stockMeta->dn_number = $request->dn_number;

            $stockMeta->save();
            $stock = Stock::firstOrCreate(
                ['store_id' => $request->store_id, 'product_id' => $request->product_id],
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
                'type' => "STOCK",
                'lpo' => $request->lpo,
                'dn_number' => $request->dn_number,
            ]);

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
    private function createStockData(Stock $stock)
    {
        \Log::info($stock);
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
