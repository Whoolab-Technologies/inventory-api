<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Product;
use App\Services\Helpers;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
class ProductController extends Controller
{

    public function index(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $products = Product::query();

            if ($searchTerm) {
                $products->search($searchTerm);
            }

            $products = $products->get();
            return Helpers::sendResponse(200, $products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $item = Product::findOrFail($id);
            return Helpers::sendResponse(200, $item, 'Item retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'item' => 'required|string|max:255',
            'cat_id' => 'required|string|max:255|unique:products,cat_id',
            'description' => 'nullable|string|max:255',
            'unit_id' => 'required|numeric|exists:units,id',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        \Log::info($request->all());
        try {
            $item = Product::create($request->all());

            if ($request->hasFile('image')) {
                $itemId = $item->id;
                $imagePath = Helpers::uploadFile($request->file('image'), "images/products/$itemId");
                $item->image = $imagePath;
                $item->save();
            }

            $qrCode = QrCode::format('png')
                ->size(200)
                ->style('dot')
                ->eye('circle')
                ->color(0, 0, 255)
                ->margin(1)
                ->generate($item->id);
            $folderPath = 'qrcodes';
            $fileName = $item->id . '.png';
            $storagePath = storage_path("app/public/{$folderPath}");

            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            if (!file_put_contents("{$storagePath}/{$fileName}", $qrCode)) {
                throw new \Exception('Failed to store QR code.');
            }

            // Save QR Code Path
            $item->qr_code = "{$folderPath}/{$fileName}";
            $item->save();
            DB::commit();
            return Helpers::sendResponse(201, $item, 'Item created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($item)) {
                $item->delete();
            }
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }


    // public function store(Request $request)
    // {
    //     $this->validate($request, [
    //         'item' => 'required|string|max:255',
    //         'item_description' => 'required|string|max:255',
    //         'brand_id' => 'required|integer',
    //         'quantity' => 'required|numeric',
    //         'unit_id' => 'required|integer',
    //         'cost' => 'required|numeric',
    //         'remarks' => 'nullable|string',
    //     ]);

    //     try {
    //         $item = Product::create($request->all());
    //         $qrCode = QrCode::format('png')
    //             ->size(200)
    //             ->style('dot')
    //             ->eye('circle')
    //             ->color(0, 0, blue: 255)
    //             ->margin(1)
    //             ->generate($item->id);
    //         $folderPath = 'qrcodes';
    //         $fileName = $item->id . '.png';
    //         $storagePath = storage_path('app/public/' . $folderPath);
    //         if (!file_exists($storagePath)) {
    //             mkdir($storagePath, 0755, true);
    //         }
    //         file_put_contents($storagePath . '/' . $fileName, $qrCode);
    //         $item->qr_code = $folderPath . '/' . $fileName;
    //         $item->save();
    //         return Helpers::sendResponse(201, $item, 'Item created successfully');
    //     } catch (\Exception $e) {
    //         return Helpers::sendResponse(500, [], $e->getMessage());
    //     }
    // }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'item' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'unit_id' => 'required|numeric|exists:units,id',
            'remarks' => 'nullable|string',
        ]);

        try {
            $item = Product::findOrFail($id);
            $item->update($request->all());
            return Helpers::sendResponse(200, $item, 'Item updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $item = Product::findOrFail($id);
            $item->delete();
            return Helpers::sendResponse(200, [], 'Item deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function getProducts(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user->tokenCan('storekeeper')) {
                return Helpers::sendResponse(403, [], 'Access denied');
            }

            $searchTerm = $request->query('search');
            \Log::info($searchTerm);
            $products = Product::with([
                'engineersStock' => function ($query) use ($user) {
                    $query->where('store_id', $user->store_id);
                },
                'engineersStock.engineer',
            ]);

            if ($searchTerm) {
                $products->search($searchTerm);
            }

            $products = $products->get()->map(function ($product) use ($user) {
                $product->total_stock = $product->engineersStock->sum('quantity');
                return $product;
            });

            return Helpers::sendResponse(200, $products, 'Products retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

}
