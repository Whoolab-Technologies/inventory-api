<?php

namespace App\Http\Controllers\V1;
use App\Services\V1\NotificationService;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\V1\Engineer;
use App\Models\V1\InventoryDispatch;
use App\Models\V1\MaterialReturn;
use App\Models\V1\MaterialReturnDetail;
use App\Models\V1\MaterialReturnItem;
use App\Models\V1\Store;
use App\Models\V1\Storekeeper;
use App\Models\V1\Product;
use App\Models\V1\MaterialRequest;
use App\Models\V1\StockTransfer;
use App\Models\V1\PurchaseRequest;
use App\Data\StockTransferData;
use App\Data\StockTransactionData;
use App\Enums\StatusEnum;
use App\Enums\TransactionType;
use App\Enums\StockMovementType;
use App\Enums\StockMovement;
use App\Enums\TransferPartyRole;
use App\Enums\RequestType;

use App\Services\Helpers;
use App\Services\V1\MaterialRequestService;
use App\Services\V1\MaterialReturnService;
use App\Services\V1\TransactionService;
use App\Services\V1\StockTransferService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StorekeeperController extends Controller
{
    protected $materialRequestService;
    protected $materialReturnService;
    protected $transactionService;
    protected $stockTransferService;
    protected $notificationService;

    public function __construct(
        MaterialRequestService $materialRequestService,
        MaterialReturnService $materialReturnService,
        TransactionService $transactionService,
        StockTransferService $stockTransferService,
        NotificationService $notificationService
    ) {
        $this->materialRequestService = $materialRequestService;
        $this->materialReturnService = $materialReturnService;
        $this->transactionService = $transactionService;
        $this->stockTransferService = $stockTransferService;
        $this->notificationService = $notificationService;
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
                        if ($isHisStore) {
                            // $query->where('store_id', $user->store_id)->where('quantity', '>', 0);
                            $query->where('store_id', $user->store_id);
                        } else {
                            if ($storeId) {
                                $query->where('store_id', $storeId);
                            } else {
                                $query->where('store_id', '!=', $user->store_id);
                            }
                        }

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
                if ($isHisStore) {
                    $productsQuery->whereHas('stocks', function ($query) use ($user) {
                        $query->where('store_id', $user->store_id);
                    });
                } elseif ($storeId) {
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
            $storekeeper = auth()->user()->load('store');

            $searchTerm = $request->query('search');

            $materialRequestsQuery = MaterialRequest::with(['status', 'store', 'engineer', 'items.product', 'stockTransfers.items']);
            if ($storekeeper->store->type != 'central') {
                $materialRequestsQuery->where('store_id', $storekeeper->store_id);
            }

            if ($searchTerm) {
                $materialRequestsQuery->search($searchTerm);
            }

            $materialRequests = $materialRequestsQuery->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
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
            $this->notificationService->sendNotificationOnMaterialRequestUpdate($materialRequest);
            $materialRequest = $this->mapStockItemsProduct($materialRequest);
            return Helpers::sendResponse(200, $materialRequest, 'Material requests updated successfully');

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }
    private function mapStockItemsProduct($materialRequest)
    {
        $materialRequest->refresh();
        $materialRequest->load(['status', 'store', 'engineer', 'items.product', 'stockTransfers.items']);
        $materialRequest = $this->materialRequestService->mapStockItemsProduct($materialRequest);
        return $materialRequest;
    }
    public function createTransaction(Request $request)
    {
        try {
            \DB::beginTransaction();
            $materialRequest = $this->transactionService->createTransaction($request);
            $this->notificationService->sendNotificationOnMaterialIssued($materialRequest);
            $materialRequest = $this->mapStockItemsProduct($materialRequest);
            \DB::commit();
            return Helpers::sendResponse(200, $materialRequest, 'Transaction created successfully');

        } catch (\Throwable $th) {
            \DB::rollBack();
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
            $materialRequest = $this->materialRequestService->mapStockItemsProduct($materialRequest);

            return Helpers::sendResponse(200, $materialRequest, 'Material request transactions retreived successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function getTransactions(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $materialRequestId = $request->query('materialRequestId');
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

            if ($materialRequestId) {

                $stockTransfer->where(function ($q) use ($materialRequestId) {
                    $q->where(function ($q2) use ($materialRequestId) {
                        $q2->where('request_id', $materialRequestId)
                            ->where('request_type', RequestType::MR);
                    })->orWhere(function ($q2) use ($materialRequestId) {
                        $q2->where('request_id', $materialRequestId)
                            ->where('request_type', RequestType::PR);
                    });
                });
            }
            if ($searchTerm) {
                $stockTransfer->search($searchTerm);
            }

            $stockTransfer = $stockTransfer->orderBy('id', 'desc')->get();
            $stockTransfer->map(function ($transfer) {

                // Default to null
                $transfer->engineer = null;
                $transfer->purchaseRequests = null;
                // If the transfer does not have a request_id, try to get the engineer from the related StockTransaction using dn_number
                // if ($transfer->request_id == 0) {
                //     $stockTransaction = StockTransaction::where('dn_number', $transfer->dn_number)->first();
                //     if ($stockTransaction) {
                //         $transfer->engineer = $stockTransaction->engineer;
                //     }
                // }
                // Handle based on type and request_id
                if ($transfer->request_type == "MR" && $transfer->request_id > 0 && $transfer->materialRequest) {
                    $transfer->engineer = $transfer->materialRequest->engineer;
                }

                if ($transfer->request_type == "PR" && $transfer->request_id > 0 && $transfer->purchaseRequest) {
                    $transfer->engineer = $transfer->purchaseRequest->materialRequest->engineer;
                    $transfer->purchaseRequests = $transfer->purchaseRequest->purchaseRequests;
                }
                if ($transfer->request_type == "DISPATCH" && $transfer->request_id > 0 && $transfer->pickup) {
                    $transfer->engineer = $transfer->pickup->engineer;
                    //  $transfer->purchaseRequests = $transfer->purchaseRequest->purchaseRequests;
                }
                // Notes Mapping
                $transfer->notes = $transfer->notes->map(function ($item) {
                    $createBy = $item->createdBy;
                    $store = $createBy?->store;
                    unset($createBy->store);

                    return [
                        "id" => $item->id,
                        "note" => $item->notes,
                        "created_by" => array_merge($createBy->toArray(), ['created_type' => $item->created_type]),
                        "store" => $store,
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
            $transaction->refresh();
            $transaction = $transaction->load([
                'items.product',
                'fromStore',
                'toStore',
                'status',
                'materialRequest',
            ]);
            $materialRequest = $transaction->materialRequest;
            $materialRequest = $this->mapStockItemsProduct($materialRequest);
            $this->notificationService->sendNotificationOnMaterialReceived($transaction);
            $transaction->engineer = $materialRequest->engineer;
            $response = [
                'material_request' => $materialRequest,
                'transaction' => $transaction,
            ];

            return Helpers::sendResponse(200, $response, 'Transaction updated successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    public function createInventoryDispatch(Request $request)
    {
        try {
            $storekeeper = auth()->user();
            $pickup = $this->transactionService->createInventoryDispatch($request, $storekeeper);
            if ($pickup->self ?? false) {
                $this->notificationService->sendNotificationOnMaterialPickup($pickup);
            }
            return Helpers::sendResponse(200, $pickup, 'Dispatch created successfully');
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
            $storeId = $storekeeper->store_id;
            $engineers = Engineer::with([
                'stocks' => function ($query) use ($storeId) {
                    $query->where('store_id', $storeId)
                        ->where('quantity', '>', 0);
                },
                'stocks.product'
            ])
                ->where('store_id', $storeId)
                ->get()
                ->map(function ($engineer) {
                    $engineer->products = $engineer->stocks->map(function ($stock) {
                        $productArray = $stock->product ? $stock->product->toArray() : [];
                        $productArray['quantity'] = $stock->quantity;
                        return $productArray;
                    })->filter()->values();

                    unset($engineer->stocks);
                    return $engineer;
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
                    'files',
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
            if (is_string($request->products)) {
                $decoded = json_decode($request->products, true);
                $request->merge(['products' => $decoded]);
            }

            $materialReturn = $this->materialReturnService->createMaterialReturns($request);
            $this->notificationService->sendNotificationOnMaterialReturnToCentralStore($materialReturn, $request->engineer_id);
            return Helpers::sendResponse(200, $materialReturn, 'Material return created successfully');

        } catch (\Throwable $th) {
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

            // $stores = Store::with([
            //     'engineers.stocks' => function ($query) use ($productIds) {
            //         $query->whereIn('product_id', $productIds)
            //             ->select('id', 'store_id', 'engineer_id', 'product_id', 'quantity');
            //     },
            //     'stocks' => function ($query) use ($productIds) {
            //         $query->whereIn('product_id', $productIds)
            //             ->where('engineer_id', 0) // Central stock only
            //             ->select('id', 'store_id', 'engineer_id', 'product_id', 'quantity');
            //     }
            // ])->where('id', $storeId)
            //     ->get();
            // foreach ($stores as $store) {
            //     $engineersData = [];

            //     // Central stock (engineer_id = 0)
            //     $centralProducts = [];
            //     foreach ($productIds as $productId) {
            //         $quantity = $store->stocks->firstWhere('product_id', $productId)->quantity ?? 0;
            //         $centralProducts[] = [
            //             'id' => $productId,
            //             'quantity' => $quantity,
            //         ];
            //     }

            //     $engineersData[] = [
            //         'id' => 0,
            //         'products' => $centralProducts,
            //     ];

            //     // Engineer-wise stock
            //     foreach ($store->engineers as $engineer) {
            //         $products = [];
            //         foreach ($productIds as $productId) {
            //             $quantity = $engineer->stocks->firstWhere('product_id', $productId)->quantity ?? 0;
            //             $products[] = [
            //                 'id' => $productId,
            //                 'quantity' => $quantity,
            //             ];
            //         }

            //         $engineersData[] = [
            //             'id' => $engineer->id,
            //             'products' => $products,
            //         ];
            //     }

            //     $response[] = [
            //         'id' => $store->id,
            //         'name' => $store->name ?? '',
            //         'engineers' => $engineersData,
            //     ];
            // }

            // Load stores with engineers and relevant stocks
            $stores = Store::with([
                'engineers.stocks' => function ($q) use ($productIds) {
                    $q->whereIn('product_id', $productIds)
                        ->select('id', 'store_id', 'engineer_id', 'product_id', 'quantity');
                },
                'stocks' => function ($q) use ($productIds) {
                    $q->whereIn('product_id', $productIds)
                        ->where('engineer_id', 0) // central stock
                        ->select('id', 'store_id', 'engineer_id', 'product_id', 'quantity');
                }
            ])->get();

            $response = [];

            foreach ($stores as $store) {
                $productsData = [];
                $productsMap = [];

                foreach ($productIds as $productId) {

                    $engineersList = [];

                    // Check if this store is central store
                    if ($store->is_central_store) {
                        // Central stock for this product
                        $centralStock = $store->stocks->firstWhere('product_id', $productId);
                        $centralQuantity = $centralStock ? $centralStock->quantity : 0;

                        $engineersList[] = [
                            'id' => 0,
                            'name' => 'Central',
                            'quantity' => $centralQuantity,
                        ];
                    }

                    // Loop through engineers for this store
                    foreach ($store->engineers as $engineer) {
                        $stock = $engineer->stocks->where('quantity', '>', 0)
                            ->firstWhere('product_id', $productId);

                        if ($stock)
                            $engineersList[] = [
                                'id' => $engineer->id,
                                'name' => $engineer->name ?? '',
                                'quantity' => $stock->quantity
                            ];
                    }

                    // Total quantity = sum of all engineers' quantities (including central if applicable)
                    $totalQuantity = array_sum(array_column($engineersList, 'quantity'));
                    $product = [
                        'id' => $productId,
                        'total_quantity' => $totalQuantity,
                        'engineers' => $engineersList,
                    ];
                    $productsData[] = $productsMap[$productId] = $product;
                }

                $response[] = [
                    'id' => $store->id,
                    'name' => $store->name ?? '',
                    'is_central_store' => $store->is_central_store ?? '',
                    //'products' => $productsData,
                    'productsMap' => $productsMap,
                ];
            }

            return Helpers::sendResponse(200, $response);

        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }


    public function getEngineersMaterialReturns(Request $request)
    {
        try {
            $user = auth()->user();
            $searchTerm = $request->query('search');

            $materialReturnsQuery = MaterialReturn::with([
                'status',
                'toStore',
                'fromStore',
                'details.engineer',
                'items.product',
            ])
                ->where('to_store_id', $user->store->id)
                ->where('from_store_id', $user->store->id);
            if ($searchTerm) {
                $materialReturnsQuery->search($searchTerm);
            }
            $materialReturns = $materialReturnsQuery->orderByDesc('id')
                ->get();
            return Helpers::sendResponse(200, $materialReturns, 'Material Returns retrieved successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());

        }
    }



    public function getReturnableProducts(Request $request, $id)
    {
        try {

            $dispatches = InventoryDispatch::where('engineer_id', $id)
                ->with(['items', 'items.product'])
                ->get();

            $products = $dispatches->flatMap(function ($dispatch) {
                return $dispatch->items;
            })
                ->groupBy('product_id')
                ->map(function ($items, $productId) {
                    $product = $items->first()->product;
                    $product->quantity = $items->sum('quantity'); // Add quantity as dynamic property
                    return $product;
                })
                ->values();
            return Helpers::sendResponse(
                status: 200,
                data: $products,
                messages: "",
            );
        } catch (\Exception $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }


    public function createEngineerMaterialReturns(Request $request)
    {
        \DB::beginTransaction();
        try {
            $user = auth()->user();
            if (is_string($request->products)) {
                $decoded = json_decode($request->products, true);
                $request->merge(['products' => $decoded]);
            }
            // Validate incoming request
            $validated = $request->validate([
                'engineer_id' => 'required',
                'dn_number' => 'nullable|string|max:255',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.issued' => 'required|numeric|min:1',
            ]);

            $request->merge([
                'from_store_id' => $user->store->id,
                'to_store_id' => $user->store->id,
            ]);

            // Create Material Return
            $materialReturn = MaterialReturn::create([
                'return_number' => 'IR-' . date('Y') . '-' . str_pad(MaterialReturn::max('id') + 1, 3, '0', STR_PAD_LEFT),
                'from_store_id' => $request->from_store_id,
                'to_store_id' => $request->to_store_id,
                'dn_number' => $request->dn_number,
                'status_id' => StatusEnum::RECEIVED->value
            ]);

            // Create Material Return Detail
            $materialReturnDetail = MaterialReturnDetail::create([
                'material_return_id' => $materialReturn->id,
                'engineer_id' => $request->engineer_id,
            ]);
            foreach ($validated['products'] as $product) {
                // Create Material Return Item
                $materialReturnItem = MaterialReturnItem::create([
                    'material_return_id' => $materialReturn->id,
                    'material_return_detail_id' => $materialReturnDetail->id,
                    'product_id' => $product['product_id'],
                    'issued' => $product['issued'],
                ]);
            }

            $this->materialReturnService->uploadMaterialReturnImages($request, $materialReturn, 'transfer');
            $this->createStockTransferWithItems($request, $materialReturn, $user, $validated['products']);

            \DB::commit();

            // Load relations
            $materialReturn->load([
                'status',
                'fromStore',
                'toStore',
                'items.product',
                'details.engineer',
                'details.items.product',
            ]);
            $this->notificationService->sendNotificationOnMaterialReturnFromEngineer($materialReturn, $request->engineer_id);
            return Helpers::sendResponse(200, $materialReturn, 'Material return created successfully');

        } catch (\Throwable $th) {
            \DB::rollBack();
            \Log::error('Material return creation failed: ' . $th->getMessage());
            return Helpers::sendResponse(500, [], $th->getMessage());
        }

    }

    private function createStockTransferWithItems(
        $request,
        $materialReturn,
        $user,
        array $items,

    ) {
        $stockTransferData = new StockTransferData(
            $request->from_store_id,
            $request->to_store_id,
            StatusEnum::COMPLETED,
            $request->dn_number,
            null,
            $materialReturn->id,
            RequestType::ENGG_RETURN,
            TransactionType::ENGG_SS,
            $request->engineer_id,
            TransferPartyRole::ENGINEER,
            $user->id,
            TransferPartyRole::SITE_STORE->value,
        );
        $transfer = $this->stockTransferService->createStockTransfer($stockTransferData);
        $engineerId = $request->engineer_id;
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $issued = $item['issued'];
            $transferItem = $this->stockTransferService->createStockTransferItem(
                $transfer->id,
                $productId,
                $issued,
                $issued,
                $issued
            );

            $this->stockTransferService->updateStock($user->store->id, $productId, $issued, $engineerId, );

            $stockTransactionData = new StockTransactionData(
                $request->from_store_id,
                $productId,
                $engineerId,
                $issued,
                StockMovementType::ENGG_RETURN,
                StockMovement::IN,
                null,
                $request->dn_number,
            );
            $this->stockTransferService->createStockTransaction($stockTransactionData);
        }

    }

    public function getPrDetails(Request $request, $id, $prId)
    {
        try {
            // Fetch Material Request (Optional, if you want to validate existence)
            $materialRequest = MaterialRequest::findOrFail($id);

            // Fetch Purchase Request with items and status
            $purchaseRequest = PurchaseRequest::with([
                'status',
                'materialRequest.status',
                'transactions.status',
                'transactions.items.product',
                'items.product'

            ])
                ->where('material_request_id', $materialRequest->id)
                ->where('id', $prId)
                ->firstOrFail();


            return Helpers::sendResponse(200, $purchaseRequest, 'Material return created successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, $th->getMessage());
        }
    }
}
