<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Stock;
use App\Services\Helpers;
use App\Models\V1\Product;
use App\Models\V1\Store;
class StockController extends Controller
{
    public function index()
    {
        try {
            $stocks = Stock::with(['store', 'product'])
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
            $stock = Stock::find($id);
            if ($stock) {
                $stock = $this->createStockData($stock);
            }
            $products = Product::all();
            $stores = Store::all();
            $response = [
                'stock' => $stock,
                'products' => $products,
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
            $stock = Stock::updateOrCreate(
                ['store_id' => $request->store_id, 'product_id' => $request->product_id],
                ['quantity' => $request->quantity]
            );
            $stock = $this->createStockData($stock);
            return Helpers::sendResponse(201, $stock);
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stock = Stock::find($id);
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
                return Helpers::sendResponse(200, ['message' => 'Stock deleted']);
            } else {
                return Helpers::sendResponse(404, [], 'Stock not found');
            }
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    private function createStockData(Stock $stock)
    {
        return [
            'id' => $stock->id,
            'store_id' => $stock->store_id,
            'store_name' => $stock->store->name,
            'product_id' => $stock->product_id,
            'product_name' => $stock->product->item,
            'quantity' => $stock->quantity,
            'unit' => $stock->product->unit->id,
            'symbol' => $stock->product->unit->symbol,
        ];
    }
}
