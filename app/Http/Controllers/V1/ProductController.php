<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Category;
use Illuminate\Http\Request;
use App\Models\V1\Product;
use App\Models\V1\Brand;
use App\Models\V1\Stock;
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
            $perPage = $request->query('per_page', 50);

            $products = Product::query();

            if ($searchTerm) {
                $products->search($searchTerm);
            }

            $paginatedProducts = $products->paginate($perPage);
            return Helpers::sendResponse(200, $paginatedProducts, 'Products retrieved successfully');
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
        try {
            $item = Product::create($request->all());
            $itemId = str_pad($item->id, 2, '0', STR_PAD_LEFT);
            if ($request->hasFile('image')) {
                $imagePath = Helpers::uploadFile($request->file('image'), "products/images/$itemId");
                $item->image = $imagePath;
                $item->save();
            }

            $qrCode = QrCode::format('png')
                ->size(200)
                ->style('dot')
                ->eye('circle')
                ->color(0, 0, 255)
                ->margin(2)
                ->generate($itemId);
            $folderPath = "qrcodes/products/$itemId";
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


    public function getProduct(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $stores = Stock::where('product_id', $id)
                ->select('store_id')
                ->selectRaw('SUM(quantity) as total_quantity')
                ->groupBy('store_id')
                ->with('store:id,name')
                ->get()
                ->map(fn($s) => [
                    'store_id' => $s->store_id,
                    'store_name' => $s->store->name ?? '',
                    'total_quantity' => $s->total_quantity
                ]);

            $engineers = Stock::where('product_id', $id)
                ->where('engineer_id', '!=', 0)
                ->select('engineer_id', 'store_id')
                ->selectRaw('SUM(quantity) as total_quantity')
                ->groupBy('engineer_id', 'store_id')
                ->with([
                    'engineer:id,first_name,last_name',
                    'store:id,name'
                ])
                ->get()
                ->map(fn($e) => [
                    'engineer_id' => $e->engineer_id,
                    'engineer_name' => $e->engineer ? $e->engineer->first_name . ' ' . $e->engineer->last_name : '',
                    'store_id' => $e->store_id,
                    'store_name' => $e->store ? $e->store->name : '',
                    'total_quantity' => $e->total_quantity
                ]);
            $response = $product->toArray();
            $response['stores'] = $stores;
            $response['engineers'] = $engineers;
            return Helpers::sendResponse(200, $response, 'Item retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function getCategoriesAndBrands(Request $request)
    {
        try {
            $data = [];
            $categories = Category::get();
            $brands = Brand::get();
            $data = [
                'categories' => $categories,
                'brands' => $brands,
            ];
            return Helpers::sendResponse(200, $data, 'Data retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
