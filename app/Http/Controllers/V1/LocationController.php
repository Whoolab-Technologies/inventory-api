<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Location;
use App\Services\Helpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    // Get all locations, filtered by store_id unless user is admin
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $locationQuery = Location::with(['product', 'store']);

            if ($user->tokenCan('storekeeper')) {
                $locationQuery->where('store_id', $user->store_id);
            }
            $locations = $locationQuery->orderByDesc('id')->get();


            return Helpers::sendResponse(
                status: 200,
                data: $locations,
                messages: 'Locations fetched successfully'
            );
        } catch (\Throwable $e) {
            return Helpers::sendResponse(
                status: 500,
                messages: $e->getMessage()
            );
        }
    }

    // Store a new location
    public function store(Request $request)
    {
        try {
            $validated = $this->validate($request, [
                'product_id' => 'required|integer|exists:products,id',
                'store_id' => 'required|integer|exists:stores,id',
                'location' => 'required|string|max:255',
                'rack_number' => 'nullable|string'
            ]);

            $exists = Location::where('store_id', $validated['store_id'])
                ->where('product_id', $validated['product_id'])
                ->exists();

            if ($exists) {
                return Helpers::sendResponse(
                    status: 409,
                    messages: 'Location for this store and product already exists'
                );
            }

            $location = Location::create($validated);
            $location->load(['product', 'store']);

            return Helpers::sendResponse(
                status: 201,
                data: $location,
                messages: 'Location created successfully'
            );
        } catch (\Throwable $e) {
            return Helpers::sendResponse(
                status: 500,
                messages: $e->getMessage(),
            );
        }
    }

    // Show a single location

    public function show($id = null)
    {
        try {
            $location = null;

            if ($id) {
                $location = Location::with(['product', 'store'])->find($id);
            }

            $products = \App\Models\V1\Product::all();

            return Helpers::sendResponse(
                status: 200,
                data: [
                    'location' => $location,
                    'products' => $products
                ],
                messages: 'Location and products fetched successfully'
            );
        } catch (\Throwable $e) {
            return Helpers::sendResponse(
                status: 500,
                messages: $e->getMessage()
            );
        }


    }

    // Update a location
    public function update(Request $request, $id)
    {
        try {
            $location = Location::findOrFail($id);

            $this->validate($request, [
                'product_id' => 'required|integer|exists:products,id',
                'store_id' => 'required|integer|exists:stores,id',
                'location' => 'required|string|max:255',
                'rack_number' => 'nullable|string'
            ]);


            $exists = Location::where('store_id', $request->store_id)
                ->where('product_id', $request->product_id)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return Helpers::sendResponse(
                    status: 409,
                    messages: 'Location for this store and product already exists'
                );
            }

            $location->update($request->only(['product_id', 'store_id', 'location', 'rack_number']));
            $location->load(['product', 'store']);

            return Helpers::sendResponse(
                status: 200,
                data: $location,
                messages: 'Location updated successfully'
            );
        } catch (\Throwable $e) {
            return Helpers::sendResponse(
                status: 500,
                messages: $e->getMessage(),

            );
        }
    }

    // Delete a location
    public function destroy($id)
    {
        try {
            $location = Location::findOrFail($id);
            $location->delete();

            return Helpers::sendResponse(
                status: 200,
                data: null,
                messages: 'Location deleted successfully'
            );
        } catch (\Throwable $e) {
            return Helpers::sendResponse(
                status: 500,
                messages: $e->getMessage()

            );
        }
    }
}
