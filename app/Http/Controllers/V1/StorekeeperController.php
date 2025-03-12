<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Storekeeper;
use App\Models\V1\Product;
use App\Models\V1\MaterialRequest;
use App\Models\V1\StockTransfer;

use App\Services\Helpers;
use App\Services\V1\MaterialRequestService;
use App\Services\V1\TransactionService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StorekeeperController extends Controller
{
    protected $materialRequestService;
    protected $transactionService;

    public function __construct(MaterialRequestService $materialRequestService, TransactionService $transactionService)
    {
        $this->materialRequestService = $materialRequestService;
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
            $user = auth()->user();
            if (!$user->tokenCan('storekeeper')) {
                return Helpers::sendResponse(403, [], 'Access denied');
            }

            $searchTerm = $request->query('search');
            $products = Product::with([
                'engineerStocks' => function ($query) use ($user) {
                    $query->where('store_id', $user->store_id);
                },
                'engineerStocks.engineer',
            ]);

            if ($searchTerm) {
                $products->search($searchTerm);
            }

            $products = $products->get()->map(function ($product) use ($user) {
                $product->total_stock = $product->engineerStocks->sum('quantity');
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
                    'store',
                    'engineer',
                    'products'
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

    public function getMaterialRequests(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $materialRequests = MaterialRequest::with(['store', 'engineer', 'items.product']);
            if ($searchTerm) {
                $materialRequests->search($searchTerm);
            }
            $materialRequests = $materialRequests->orderBy('created_at', 'desc')
                ->get();
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
                    'stockTransferItems.product',
                    'fromStore',
                    'toStore',
                    'notes',
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
                    $transfer = $this->manageTransactionData($transfer);
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
            \Log::info($request->all());
            $transaction = $this->transactionService->updateTransaction($request, $id);
            $transaction = StockTransfer::with(
                [
                    'stockTransferItems.product',
                    'fromStore',
                    'toStore',
                    'notes',
                ]
            )->where('id', $id)->first();
            $transaction = $this->manageTransactionData($transaction);
            return Helpers::sendResponse(200, $transaction, 'Transaction updated successfully');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }
    private function manageTransactionData($transfer)
    {
        $notes = $transfer->notes;
        unset($transfer->notes);
        $transfer->material_request = $transfer->materialRequestStockTransfer->materialRequest;
        $transfer->engineer = $transfer->materialRequestStockTransfer->materialRequest->engineer;
        $transfer->notes = $notes->map(function ($item) {
            $createdBy = $item->createdBy->load('store');
            $store = $createdBy->store;
            unset($createdBy->store);
            return [
                'id' => $item->id,
                'stock_transfer_id' => $item->stock_transfer_id,
                'material_request_id' => $item->material_request_id,
                'notes' => $item->notes,
                'created_by' => $createdBy,
                'store' => $store
            ];
        });
        unset($transfer->materialRequestStockTransfer);

        return $transfer;
    }

}
