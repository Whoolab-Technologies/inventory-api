<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\MaterialRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\Engineer;
use App\Models\V1\Product;
use App\Models\V1\Stock;
use App\Models\V1\EngineerStock;
use Illuminate\Support\Facades\Hash;

class EngineerController extends Controller
{

    public function index()
    {
        $engineers = Engineer::with(['store'])->get();
        return Helpers::sendResponse(
            status: 200,
            data: $engineers,
            messages: '',
        );
    }

    public function store(Request $request)
    {
        \Log::info("store engineer");
        \Log::info($request->all());

        try {
            $validated = $this->validate($request, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string',
                'email' => 'required|string|email|max:255|unique:engineers',
                'password' => 'required|string|min:6',
                'store_id' => 'required|exists:stores,id',
            ]);

            $engineer = Engineer::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'store_id' => $request->store_id,
                'password' => Hash::make($request->password),
            ]);
            return Helpers::sendResponse(
                status: 200,
                data: $engineer,
                messages: 'Shopkeeper registered successfully',
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
            $engineer = Engineer::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $engineer,
                messages: '',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
            );
        } catch (\Exception $th) {
            \Log::info(400);
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
            \Log::info("id $id");
            $engineer = Engineer::findOrFail($id);
            $this->validate($request, [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'nullable|string',
                'email' => "sometimes|required|string|email|max:255|unique:engineers,email,{$id}",
                'password' => 'nullable|string|min:6',
            ]);


            if ($request->has('password')) {
                $request->merge(['password' => Hash::make($request->password)]);
            }
            $engineer->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $engineer,
                messages: 'Engineer details updated successfully',
            );

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
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
            $engineer = Engineer::findOrFail($id);
            $engineer->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Engineer deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    public function getProducts(Request $request)
    {
        try {

            $user = auth()->user();
            if (!$user->tokenCan('engineer')) {
                return Helpers::sendResponse(403, [], 'Access denied', );
            }
            $searchTerm = $request->query('search');
            \Log::info($searchTerm);
            $products = Product::with([
                'engineersStock' => function ($query) use ($user) {
                    $query->where('store_id', $user->store_id);
                },
                'engineersStock.engineer',
                'stocks' => function ($query) use ($user) {
                    $query->where('store_id', $user->store_id);
                },
            ]);

            if ($searchTerm) {
                $products->search($searchTerm);
            }
            $products = $products->get()->map(function ($product) use ($user) {
                $product->total_stock = $product->stocks->sum('quantity');
                $product->engineer_stock = $product->engineersStock->sum('quantity');
                $product->my_stock = $product->engineersStock->where('engineer_id', $user->id)->sum('quantity');
                $product->stock_with_others = $product->engineersStock->where('engineer_id', '!=', $user->id)->sum('quantity');
                return $product;
            });

            // $storeStock = Stock::where('store_id', $user->store_id)->get()->keyBy('product_id');

            // $engineerStocks = EngineerStock::where('store_id', $user->store_id)
            //     ->get()
            //     ->groupBy('product_id');

            // // Format the response
            // $stockData = [];

            // foreach ($products as $product) {
            //     $product->total_stock = $storeStock[$product->id]->quantity ?? 0;
            //     $product->engineer_stock = (isset($engineerStocks[$product->id])) ? $engineerStocks[$product->id]->sum('quantity') ?? 0 : 0;
            //     $stockData[] = $product;
            // }


            //  $stores = $user->load(["stocks", "store.engineerStocks.stock.product", "store.stocks.product",]);
            return Helpers::sendResponse(
                status: 200,
                data: $products,
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }
    public function createMaterialRequest(Request $request)
    {
        \DB::beginTransaction();
        try {
            $user = auth()->user();
            $materialRequest = MaterialRequest::create([
                'request_number' => 'MR-' . str_pad(MaterialRequest::max('id') + 1001, 6, '0', STR_PAD_LEFT),
                'engineer_id' => $user->id,
                'store_id' => $user->store->id,
                'status' => 'pending',
            ]);

            $items = array_map(function ($item) use ($materialRequest) {
                return [
                    'material_request_id' => $materialRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }, $request->items);

            $materialRequest->items()->createMany($items);

            \DB::commit();

            return Helpers::sendResponse(
                status: 200,
                data: $materialRequest,
            );
        } catch (\Throwable $th) {
            \DB::rollBack();
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }


    public function getMaterialRequest()
    {
        try {
            $user = auth()->user();

            $materialRequests = MaterialRequest::with(['items.product'])
                ->where('engineer_id', $user->id)
                ->get()->map(function ($mr) {
                    return [
                        'id' => $mr->id,
                        'store_id' => $mr->store_id,
                        'request_number' => $mr->request_number,
                        'created_at' => $mr->created_at,
                        'items' => $mr->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product->id,
                                'product_name' => $item->product->item,
                                'product_image' => $item->product->image_url,
                                'unit' => $item->product->symbol,
                                'quantity' => $item->quantity,
                            ];
                        }),
                    ];
                });

            return Helpers::sendResponse(
                status: 200,
                data: $materialRequests,
                messages: 'Material requests retrieved successfully',
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
