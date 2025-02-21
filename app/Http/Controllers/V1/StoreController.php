<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Store;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StoreController extends Controller
{
    public function index()
    {
        try {
            $stores = Store::all();
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
                'storekeeper_id' => 'nullable|exists:storekeepers,id',
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
                'storekeeper_id' => 'nullable|exists:storekeepers,id',
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

    public function getStoresByStorekeeper(Request $request, $storekeeper_id = null)
    {

        try {
            if ($request->user()->tokenCan('admin')) {
                $id = $storekeeper_id;
            } else {
                // Storekeeper request
                $id = $request->user()->id;
            }

            $stores = Store::where('storekeeper_id', $id)->get();
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
}
