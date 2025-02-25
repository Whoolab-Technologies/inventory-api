<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
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

    function getProducts()
    {
        //  try {

        $user = auth()->user();

        if (!$user->tokenCan('engineer')) {
            return Helpers::sendResponse(403, [], 'Access denied', );
        }
        $products = Product::all();

        $storeStock = Stock::where('store_id', $user->store_id)->get()->keyBy('product_id');

        $engineerStocks = EngineerStock::where('store_id', $user->store_id)
            ->get()
            ->groupBy('product_id');

        // Format the response
        $stockData = [];

        foreach ($products as $product) {
            $product->total_stock = $storeStock[$product->id]->quantity ?? 0;
            $product->engineer_stock = (isset($engineerStocks[$product->id])) ? $engineerStocks[$product->id]->sum('quantity') ?? 0 : 0;
            $stockData[] = $product;
        }


        //  $stores = $user->load(["stocks", "store.engineerStocks.stock.product", "store.stocks.product",]);
        return Helpers::sendResponse(
            status: 200,
            data: $stockData,
        );
        // } catch (\Throwable $th) {
        //     return Helpers::sendResponse(
        //         status: 400,
        //         data: [],
        //         messages: $th->getMessage(),
        //     );
        // }
    }
}
