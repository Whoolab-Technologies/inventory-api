<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\MaterialRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\Engineer;
use App\Models\V1\Product;
use App\Models\V1\Store;
use App\Models\V1\StockTransfer;
use Illuminate\Support\Facades\Hash;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
class EngineerController extends Controller
{

    public function index()
    {
        $engineers = Engineer::with(['store', 'department'])->get();
        return Helpers::sendResponse(
            status: 200,
            data: $engineers,
            messages: '',
        );
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validate($request, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string',
                'email' => 'required|string|email|max:255|unique:engineers',
                'password' => 'required|string|min:6',
                'store_id' => 'required|exists:stores,id',
                'department_id' => 'required|exists:departments,id',
            ]);

            $engineer = Engineer::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'store_id' => $request->store_id,
                'department_id' => $request->department_id,
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
                data: $engineer->load('department'),
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
            $all = $request->query('all');
            if (!empty($all) && $all == true) {
                $products = Product::all();
                return Helpers::sendResponse(
                    status: 200,
                    data: $products,
                );
            }
            $searchTerm = $request->query('search');
            $storeId = $request->query('store_id');
            $engineerId = $request->query('engineer_id');
            $isHisShock = !$engineerId || $engineerId == $user->id;

            $productsQuery = Product::
                whereHas('engineerStocks', function ($query) use ($engineerId) {
                    $query->where('quantity', '>', 0);
                    if ($engineerId) {
                        $query->where('engineer_id', $engineerId);
                    }
                })
                ->with([
                    'engineerStocks' => function ($query) use ($user, $isHisShock, $engineerId) {

                        $query->where('quantity', '>', 0)
                            ->where('store_id', $user->store_id);
                        if (!$isHisShock && $engineerId) {
                            $query->where('engineer_id', $engineerId);
                        }
                    },
                    'engineerStocks.engineer',
                    'stocks' => function ($query) use ($user) {
                        $query->where('store_id', $user->store_id);
                    },
                ]);

            if ($searchTerm) {
                $productsQuery->search($searchTerm);
            }

            $products = $productsQuery->get()->map(function ($product) use ($user, $engineerId, $isHisShock) {
                $product->total_stock = $product->stocks->sum('quantity');
                $product->engineer_stock = $product->engineerStocks->sum('quantity');
                if ($isHisShock) {
                    $product->my_stock = $product->engineerStocks->where('engineer_id', $user->id)->sum('quantity');
                } else {
                    $product->my_stock = $product->engineerStocks->where('engineer_id', $engineerId)->sum('quantity');
                }
                $product->stock_with_others = $product->engineerStocks->where('engineer_id', '!=', ($isHisShock ? $user->id : $engineerId))->sum('quantity');
                return $product;
            });
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

            // Generate request number
            $materialRequest = MaterialRequest::create([
                'request_number' => 'MR-' . str_pad(MaterialRequest::max('id') + 1001, 6, '0', STR_PAD_LEFT),
                'engineer_id' => $user->id,
                'store_id' => $user->store->id,
                'status' => 'pending',
            ]);
            // Process items
            $items = array_map(function ($item) use ($materialRequest) {
                return [
                    'material_request_id' => $materialRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }, $request->items);

            $materialRequest->items()->createMany($items);
            $materialRequest = $materialRequest->load("items.product");
            $materialRequest = [
                'id' => $materialRequest->id,
                'store_id' => $materialRequest->store_id,
                'request_number' => $materialRequest->request_number,
                'created_at' => $materialRequest->created_at,
                'status' => $materialRequest->status,
                'items' => $materialRequest->items->map(function ($item) {
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

            \DB::commit();
            return Helpers::sendResponse(
                status: 200,
                data: $materialRequest,
            );
        } catch (\Throwable $th) {
            \DB::rollBack();
            \Log::error("Error: " . $th->getMessage());
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
                ->orderBy('created_at', 'desc')
                ->get()->map(function ($mr) {
                    return [
                        'id' => $mr->id,
                        'store_id' => $mr->store_id,
                        'request_number' => $mr->request_number,
                        'created_at' => $mr->created_at,
                        'status' => $mr->status,
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

    public function getDashboardData(Request $request)
    {
        try {
            $data = array();
            $engineer = auth()->user()->load('store');
            $outOfStockProducts = Product::whereHas('engineerStocks', function ($query) use ($engineer) {
                $query->where('store_id', $engineer->store->id)
                    ->where('quantity', '<=', 0);
            })->with('unit')->get();
            $material_requests = MaterialRequest::with(['items'])
                ->where('engineer_id', $engineer->id)
                //->where('status', "pending")
                ->orderBy('created_at', 'desc')
                ->get()->map(function ($mr) {
                    return [
                        'id' => $mr->id,
                        'store_id' => $mr->store_id,
                        'request_number' => $mr->request_number,
                        'created_at' => $mr->created_at,
                        'status' => $mr->status,
                        'items' => $mr->items,
                    ];
                });

            $data = [
                'id' => $engineer->id,
                'user' => $engineer,
                'material_requests' => $material_requests,
                'out_of_stock_products' => $outOfStockProducts
            ];
            return Helpers::sendResponse(200, $data, 'Products retrieved successfully');

        } catch (\Throwable $th) {


            return Helpers::sendResponse(500, [], $th->getMessage());
        }

    }

    public function getTransactions(Request $request)
    {
        try {

            $engineerId = auth()->id();

            $stockTransfers = StockTransfer::whereHas('materialRequestStockTransfer.materialRequest', function ($query) use ($engineerId) {
                $query->where('engineer_id', $engineerId);
            })
                ->with(['stockTransferItems.product']) // Load stock transfer items and product details
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transfer) {
                    return [
                        "id" => $transfer->id,
                        "from_store_id" => $transfer->from_store_id,
                        "to_store_id" => $transfer->to_store_id,
                        "status" => $transfer->status,
                        "remarks" => $transfer->remarks,
                        "created_at" => $transfer->created_at,
                        "notes" => $transfer->notes->map(function ($item) {
                            $createBy = $item->createdBy;
                            $store = $item->createdBy->store;
                            unset($createBy->store);
                            return [
                                "id" => $item->id,
                                "note" => $item->notes,
                                "created_by" => array_merge($createBy->toArray(), ['created_type' => $item->created_type]),
                                "store" => $store
                            ];
                        }),

                        "items" => $transfer->stockTransferItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $item->product->item,
                                'product_image' => $item->product->image_url,
                                'unit' => $item->product->symbol,
                                'requested_quantity' => $item->requested_quantity,
                                'issued_quantity' => $item->issued_quantity,
                                'received_quantity' => $item->received_quantity,
                            ];
                        }),
                        "material_request" => $transfer->materialRequestStockTransfer->materialRequest,
                    ];

                });
            return Helpers::sendResponse(200, $stockTransfers, 'Transactions retrieved successfully');

        } catch (\Throwable $th) {


            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getEngineersAndStores(Request $request)
    {
        try {
            $data = [];
            $user = auth()->user();
            // Fetch stores and engineers
            $stores = Store::where('id', '!=', $user->store_id)->get();
            $engineers = Engineer::
                where('store_id', $user->store_id)
                ->where('id', '!=', $user->id)
                ->get();
            $data = [
                'stores' => $stores,
                'engineers' => $engineers,
            ];
            return Helpers::sendResponse(200, $data, 'Engineers and stores retrieved successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }
}
