<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Engineer;
use App\Models\V1\InventoryDispatch;
use App\Models\V1\MaterialReturn;
use App\Models\V1\PurchaseRequest;
use App\Models\V1\Store;
use Illuminate\Http\Request;
use App\Models\V1\Storekeeper;
use App\Models\V1\Product;
use App\Models\V1\MaterialRequest;
use App\Models\V1\StockTransfer;
use App\Models\V1\Stock;

use App\Services\Helpers;
use App\Services\V1\MaterialRequestService;
use App\Services\V1\MaterialReturnService;
use App\Services\V1\TransactionService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StorekeeperController extends Controller
{
    protected $materialRequestService;
    protected $materialReturnService;
    protected $transactionService;

    public function __construct(
        MaterialRequestService $materialRequestService,
        MaterialReturnService $materialReturnService,
        TransactionService $transactionService
    ) {
        $this->materialRequestService = $materialRequestService;
        $this->materialReturnService = $materialReturnService;
        $this->transactionService = $transactionService;
    }

    public function index()
    {
        $storekeepers = Storekeeper::with(['store'])->get();
        return Helpers::sendResponse(
            status: 200,
            data: $storekeepers,
            messages: '',
        );
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string',
                'store_id' => 'required|exists:stores,id',
                'email' => 'required|string|email|max:255|unique:engineers',
                'password' => 'required|string|min:6',
            ]);

            $storekeeper = Storekeeper::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'store_id' => $request->store_id,
            ]);

            // $token = $storekeeper->createToken('storekeeper-token', ['storekeeper'])->plainTextToken;
            // $response = [
            //     'user' => $storekeeper,
            //     'token' => $token,
            // ];
            return Helpers::sendResponse(
                status: 200,
                data: $storekeeper,
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

            $storekeeper = Storekeeper::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $storekeeper,
                messages: '',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Storekeeper not found',
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
            $storekeeper = Storekeeper::findOrFail($id);
            $this->validate($request, [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'nullable|string',
                'store_id' => 'sometimes|exists:stores,id',
                'email' => "sometimes|required|string|email|max:255|unique:storekeepers,email,{$id}",
                'password' => 'nullable|string|min:6',
            ]);


            if ($request->has('password')) {
                $request->merge(['password' => Hash::make($request->password)]);
            }
            $storekeeper->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $storekeeper,
                messages: 'Storekeeper details updated successfully',
            );

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Storekeeper not found',
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
            $storekeeper = Storekeeper::findOrFail($id);
            $storekeeper->delete();
            return response()->json(['message' => 'Storekeeper deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Storekeeper not found',
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
            // $user = auth()->user();

            // if (!$user->tokenCan('storekeeper')) {
            //     return Helpers::sendResponse(403, [], 'Access denied');
            // }

            // $searchTerm = $request->query('search');
            // $storeId = $request->query('store_id'); // Optional store filter
            // $engineerId = $request->query('engineer_id'); // Optional engineer filter

            // // Determine if the user is viewing their own store's stock
            // $isHisStore = !$storeId || $storeId == $user->store_id;
            // $productsQuery = Product::with(['stocks', 'engineerStocks']);

            // // Apply stock filtering
            // if (!$user->store->is_central_store) {
            //     $productsQuery->whereHas('stocks', function ($query) use ($user, $storeId, $isHisStore) {
            //         if ($isHisStore) {
            //             $query->where('store_id', $user->store_id)->where('quantity', '>', 0);
            //         } elseif ($storeId) {
            //             $query->where('store_id', $storeId);
            //         } else {
            //             $query->where('store_id', '!=', $user->store_id);
            //         }
            //     });
            // } elseif ($storeId) {
            //     $productsQuery->whereHas('stocks', fn($query) => $query->where('store_id', $storeId));
            // }

            // // Apply engineer filtering
            // if ($engineerId) {
            //     $productsQuery->whereHas('engineerStocks', fn($query) => $query->where('engineer_id', $engineerId));
            // }

            // // Apply search filter
            // if ($searchTerm) {
            //     $productsQuery->search($searchTerm);
            // }

            // // Fetch products and calculate total stock
            // $products = $productsQuery->get()->map(function ($product) {
            //     $product->total_stock = $product->stocks->sum('quantity');
            //     return $product;
            // });

            $user = auth()->user();


            if (!$user->tokenCan('storekeeper')) {
                return Helpers::sendResponse(403, [], 'Access denied');
            }

            $searchTerm = $request->query('search');
            $storeId = $request->query('store_id');
            $engineerId = $request->query('engineer_id');

            $isHisStore = $storeId == $user->store_id;
            $productsQuery = Product::with([
                'stocks' => function ($query) use ($user, $storeId, $isHisStore, $engineerId) {
                    if ($user->store && !$user->store->is_central_store) {
                        \Log::info("for Site ");
                        if ($isHisStore) {
                            \Log::info("own store ");
                            // $query->where('store_id', $user->store_id)->where('quantity', '>', 0);
                            $query->where('store_id', $user->store_id);
                        } else {
                            \Log::info("for other stores ");
                            if ($storeId) {
                                \Log::info("for other stores wirh storeId $storeId");
                                $query->where('store_id', $storeId);
                            } else {
                                \Log::info("for other stores wthout storeId");
                                $query->where('store_id', '!=', $user->store_id);
                            }
                        }
                        \Log::info("end");
                    } elseif ($user->store->is_central_store) {

                        if ($storeId && $storeId != $user->store_id) {
                            $query->where('store_id', $storeId);
                        } else {
                            $query->where('store_id', $user->store_id);
                        }
                    }
                    if ($engineerId) {
                        $query->where('engineer_id', $engineerId);
                    }
                },
                // 'engineerStocks' => function ($query) use ($user, $engineerId) {

                // }
            ]);

            if ($user->store && !$user->store->is_central_store) {
                \Log::info("productsQuery for site store ");
                if ($isHisStore) {
                    \Log::info("productsQuery for own site store ");
                    $productsQuery->whereHas('stocks', function ($query) use ($user) {
                        $query->where('store_id', $user->store_id);
                    });
                } elseif ($storeId) {
                    \Log::info("productsQuery for other sites with storeId $storeId");
                    $productsQuery->whereHas('stocks', function ($query) use ($storeId) {
                        $query->where('store_id', $storeId);
                    });
                }

            } elseif ($user->store->is_central_store && $storeId) {
                $productsQuery->whereHas('stocks', function ($query) use ($storeId) {
                    $query->where('store_id', $storeId);
                });
            }
            if ($engineerId) {
                $productsQuery->whereHas('stocks', fn($query) => $query->where('engineer_id', $engineerId));
            }
            if ($searchTerm) {
                $productsQuery->search($searchTerm);
            }

            $products = $productsQuery->get()->map(function ($product) use ($user) {
                // if ($user->store->is_central_store) {
                //     $product->total_stock = $product->stocks->sum('quantity');
                // } else {
                //     $product->total_stock = $product->engineerStocks->sum('quantity');
                // }
                $product->total_stock = $product->stocks->sum('quantity');
                return $product;
            });

            return Helpers::sendResponse(200, $products, 'Products retrieved successfully');


        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Item not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }



    public function getDashboardData(Request $request)
    {
        try {
            $data = array();
            $storekeeper = auth()->user()->load('store');
            $data = [
                'user' => $storekeeper,
            ];
            if ($storekeeper->store->type === 'central') {
                $materialRequests = MaterialRequest::with([
                    'status',
                    'store',
                    'engineer',
                    'products',
                    'stockTransfers'
                ])
                    // ->where('status', 'pending')
                    ->orderBy('created_at', 'desc')
                    ->get();
                $data['material_requests'] = $materialRequests;
            }
            $outOfStockProducts = Product::whereDoesntHave('engineerStocks', function ($query) use ($storekeeper) {
                $query->where('store_id', $storekeeper->store->id)
                    ->where('quantity', '>', 0);
            })->get();
            $data['out_of_stock_products'] = $outOfStockProducts;
            return Helpers::sendResponse(200, $data, 'Products retrieved successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }

    }

    // public function getMaterialRequests(Request $request)
    // {
    //     try {
    //         $searchTerm = $request->query('search');
    //         $materialRequests = MaterialRequest::with(['store', 'engineer', 'items.product', 'stockTransfer.items']);
    //         if ($searchTerm) {
    //             $materialRequests->search($searchTerm);
    //         }
    //         $materialRequests = $materialRequests->orderBy('created_at', 'desc')
    //             ->get();
    //         return Helpers::sendResponse(200, $materialRequests, 'Material requests retrieved successfully');
    //     } catch (\Throwable $th) {
    //         return Helpers::sendResponse(500, [], $th->getMessage());
    //     }
    // }


    public function getMaterialRequests(Request $request)
    {
        try {
            $searchTerm = $request->query('search');

            $materialRequests = MaterialRequest::with(['status', 'store', 'engineer', 'items.product', 'stockTransfers.items']);

            if ($searchTerm) {
                $materialRequests->search($searchTerm);
            }

            $materialRequests = $materialRequests->orderBy('created_at', 'desc')->get();
            $materialRequests = $materialRequests->map(function ($request) {
                // Normalize stockTransfers to a collection of items
                $stockTransfers =
                    $request->stockTransfers;

                $allStockItems = $stockTransfers
                    ->pluck('items')
                    ->flatten(1)
                    ->groupBy('product_id');

                // Map each item and sum issued/received quantities
                $request->items = collect($request->items)->map(function ($item) use ($allStockItems) {
                    $stockGroup = $allStockItems->get($item->product_id);

                    $item->requested_quantity = $stockGroup ? $stockGroup->first()->requested_quantity ?? $item->quantity : $item->quantity;
                    $item->issued_quantity = $stockGroup ? $stockGroup->sum('issued_quantity') : 0;
                    $item->received_quantity = $stockGroup ? $stockGroup->sum('received_quantity') : 0;

                    return $item;
                });

                return $request;
            });
            return Helpers::sendResponse(200, $materialRequests, 'Material requests retrieved successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }


    public function updateMaterialrequest(Request $request, $id)
    {
        try {
            $materialRequest = $this->materialRequestService->updateMaterialRequest($request, $id);
            return Helpers::sendResponse(200, $materialRequest, 'Material requests updated successfully');

        } catch (\Throwable $th) {
            \Log::info("error " . $th->getMessage());
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getMaterialRequestTransactions(Request $request, $id)
    {
        try {
            $materialRequest = MaterialRequest::with([
                'status',
                'items',
                'store',
                'engineer',
                'items.product',
                'purchaseRequests',
                'stockTransfers' => function ($q) {
                    $q->whereIn('request_type', ['MR', 'PR']);
                },
                'stockTransfers.fromStore',
                'stockTransfers.toStore',
                'stockTransfers.status',
                'stockTransfers.items',
                'stockTransfers.notes',
                'stockTransfers.files',
                'stockTransfers.items.product'
            ])->findOrFail($id);

            $stockItems = collect($materialRequest->stockTransfers ?? [])
                ->pluck('items')
                ->flatten(1)
                ->groupBy('product_id');

            $materialRequestItems = $this->materialRequestService->mapStockItemsProduct($materialRequest, $stockItems);
            $materialRequest->setRelation('items', $materialRequestItems);

            return Helpers::sendResponse(200, $materialRequest, 'Material request transactions retreived successfully');
        } catch (\Throwable $th) {
            \Log::info("error " . $th->getMessage());
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }



    public function getTransactions(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $storekeeper = auth()->user()->load('store');

            $stockTransfer = StockTransfer::with(
                [
                    'items.product',
                    'fromStore',
                    'toStore',
                    'files',
                    'materialRequest',
                    'purchaseRequest',
                    'status',
                ]
            );

            if ($storekeeper->store->type != 'central') {
                $stockTransfer = $stockTransfer->where('to_store_id', $storekeeper->store_id);
            }

            if ($searchTerm) {
                $stockTransfer->search($searchTerm);
            }

            $stockTransfer = $stockTransfer->orderBy('created_at', 'desc');

            $stockTransfer = $stockTransfer->get()
                ->map(function ($transfer) {
                    $transfer->engineer = $transfer->materialRequest->engineer;

                    // if ($transfer->type == "MR") {
                    //     $transfer->engineer = $transfer->materialRequest->engineer;
                    // }
                    // if ($transfer->type == "PR" && $transfer->materialRequest) {
                    //     $materialRequest = $transfer->materialRequest;
                    //     $transfer->engineer = $materialRequest->engineer;
                    //     $transfer->purchaseRequests = $materialRequest->purchaseRequests;
                    // }
    
                    $transfer->notes = $transfer->notes->map(function ($item) {
                        $createBy = $item->createdBy;
                        $store = $item->createdBy->store;
                        unset($createBy->store);
                        return [
                            "id" => $item->id,
                            "note" => $item->notes,
                            "created_by" => array_merge($createBy->toArray(), ['created_type' => $item->created_type]),
                            "store" => $store
                        ];
                    });
                    return $transfer;
                });
            return Helpers::sendResponse(200, $stockTransfer, 'Transactions retrieved successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function updateTransaction(Request $request, $id)
    {
        try {
            $transaction = $this->transactionService->updateTransaction($request, $id);
            $transaction = $transaction->load([
                'items.product',
                'fromStore',
                'toStore',
                'status',
                'materialRequest',
            ]);
            $transaction->engineer = $transaction->materialRequest->engineer;
            return Helpers::sendResponse(200, $transaction, 'Transaction updated successfully');
        } catch (\Throwable $th) {
            \Log::info($th->getMessage());
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function createInventoryDispatch(Request $request)
    {
        try {
            $storekeeper = auth()->user();
            $inventoryDispatch = $this->transactionService->createInventoryDispatch($request, $storekeeper);
            return Helpers::sendResponse(200, $inventoryDispatch, 'Diapatch created successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getInventoryDispatches(Request $request)
    {
        try {
            $storekeeper = auth()->user();
            $inventoryDispatches = InventoryDispatch::with(['items.product', 'store', 'engineer', 'files'])
                ->where('store_id', $storekeeper->store_id)
                ->orderBy('created_at', 'desc')
                ->get();
            return Helpers::sendResponse(200, $inventoryDispatches, 'Diapatches retrieved successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getEngineers(Request $request)
    {
        try {
            $storekeeper = auth()->user();
            $engineers = Engineer::with('stocks.product')->where('store_id', $storekeeper->store_id)->get()
                ->map(callback: function ($item) {
                    $item->products = $item->stocks->map(function ($stock) {
                        $temp = $stock;
                        $stock = $stock->product;
                        $stock->quantity = $temp->quantity;
                        return $stock;
                    });
                    unset($item->stocks);
                    return $item;
                });
            return Helpers::sendResponse(200, $engineers, 'Engineers retrieved successfully');

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
            $engineers = $user->store->is_central_store ? Engineer::all() : Engineer::where('store_id', $user->store_id)->get();
            $data = [
                'stores' => $stores,
                'engineers' => $engineers,
            ];
            return Helpers::sendResponse(200, $data, 'Engineers and stores retrieved successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getStores(Request $request)
    {
        try {
            $stores = Store::all();
            return Helpers::sendResponse(200, $stores, 'Stores retrieved successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getMaterialReturns(Request $request)
    {
        try {
            $user = auth()->user();
            $query = MaterialReturn::
                with([
                    'status',
                    'toStore',
                    'fromStore',
                    'details.engineer',
                    'details.items.product',
                ]);
            if (!$user->store->is_central_store) {
                $query = $query->where('from_store_id', $user->store->id);
            }
            $materialReturns = $query->orderByDesc('id')
                ->get();
            return Helpers::sendResponse(200, $materialReturns, 'Material Returns retrieved successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());

        }
    }

    public function postMaterialReturns(Request $request)
    {
        try {
            $user = auth()->user();
            $centralStore = Store::where('type', 'central')->first();
            $request->merge([
                'from_store_id' => $user->store->id,
                'to_store_id' => $centralStore->id
            ]);
            $materialReturn = $this->materialReturnService->createMaterialReturns($request);
            return Helpers::sendResponse(200, $materialReturn, 'Material return created successfully');

        } catch (\Throwable $th) {
            \Log::info($th->getMessage());
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }
    public function updateMaterialReturns(Request $request, $id)
    {
        try {

            $materialReturn = $this->materialReturnService->updateMaterialReturns($id, $request);
            return Helpers::sendResponse(200, $materialReturn, 'Material return updated successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }


    public function getAvailableStock(Request $request)
    {
        $storeId = $request->input('store');
        $productIds = $request->input('products');
        try {
            if (!$storeId || !is_array($productIds)) {
                return Helpers::sendResponse(400, [], 'Invalid parameters');
            }

            // Fetch quantities for given products in the store
            $stocks = Stock::where('store_id', $storeId)
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'quantity')
                ->get()
                ->groupBy('product_id')
                ->map(fn($rows) => $rows->sum('quantity'));

            $stockMap = [];
            foreach ($productIds as $productId) {
                $stockMap[$productId] = $stocks[$productId] ?? 0;
            }

            return Helpers::sendResponse(200, $stockMap);
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

}
