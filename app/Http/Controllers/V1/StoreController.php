<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Product;
use Illuminate\Http\Request;
use App\Models\V1\Store;
use App\Models\V1\ProductMinStock;
use App\Services\Helpers;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StoreController extends Controller
{
    public function index()
    {
        try {
            $stores = Store::with(['engineers', 'storekeepers'])->get();
            return Helpers::sendResponse(
                status: 200,
                data: $stores,
                messages: '',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'type' => 'required|in:site,central',
            ]);

            $store = Store::create($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $store,
                messages: 'Store created successfully',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function show($id)
    {
        try {
            $store = Store::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $store,
                messages: '',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Store not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $store = Store::findOrFail($id);
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'location' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:site,central',
            ]);

            $store->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $store,
                messages: 'Store updated successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Store not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function destroy($id)
    {
        try {
            $store = Store::findOrFail($id);
            $store->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Store deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Store not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function getProductMinStock(Request $request)
    {
        try {
            // $response = ['stocks' => [], 'products' => []];
            $this->validate($request, [
                'store_id' => 'required|exists:stores,id',
            ]);
            $storeId = $request->query('store_id');
            $stocks = ProductMinStock::with(['product'])
                ->where('store_id', $storeId)
                ->get();
            // $products = Product::get();
            $stocks = $stocks->map(function ($item) {
                $product = $item->product;
                $product->min_stock_qty = $item->min_stock_qty;
                return $product;
            });
            //   $response = ['stocks' => $stocks, 'products' => $products];
            return Helpers::sendResponse(
                status: 200,
                data: $stocks,
                messages: '',
            );
        } catch (ValidationException $th) {
            return Helpers::sendResponse(
                status: 422,
                data: [],
                messages: $th->getMessage(),
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }
    public function manageMinStock(Request $request)
    {

        try {
            $this->validate($request, [
                'product_id' => 'required|exists:products,id',
                'store_id' => 'required|exists:stores,id',
                'min_stock_qty' => 'required|integer|min:0',
            ]);

            if ($request->min_stock_qty == 0) {
                ProductMinStock::where('product_id', $request->product_id)
                    ->where('store_id', $request->store_id)
                    ->delete();

                return Helpers::sendResponse(
                    status: 200,
                    data: [],
                    messages: 'Min stock entry removed successfully.',
                );
            }
            $minStock = ProductMinStock::updateOrCreate(
                [
                    'product_id' => $request->product_id,
                    'store_id' => $request->store_id,
                ],
                [
                    'min_stock_qty' => $request->min_stock_qty,
                ]
            );

            $product = $minStock->product;
            $product->min_stock_qty = $minStock->min_stock_qty;
            return Helpers::sendResponse(
                status: 200,
                data: $product,
                messages: 'Min stock added successfully',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }
}
