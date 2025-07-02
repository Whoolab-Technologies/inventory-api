<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Supplier;
use App\Services\Helpers;

class SupplierController extends Controller
{
    // List all suppliers
    public function index()
    {
        try {
            $suppliers = Supplier::all();
            return Helpers::sendResponse(200, $suppliers, 'Suppliers fetched successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    // Store a new supplier
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'contact' => 'required|string|max:255',
                'address' => 'required|string|max:255',
            ]);

            $supplier = Supplier::create($validated);

            return Helpers::sendResponse(201, $supplier, 'Supplier created successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    // Show a single supplier
    public function show($id)
    {
        try {
            $supplier = Supplier::find($id);
            if (!$supplier) {
                return Helpers::sendResponse(404, [], 'Supplier not found.');
            }
            return Helpers::sendResponse(200, $supplier, 'Supplier fetched successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    // Update a supplier
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|nullable|email|max:255',
                'contact' => 'sometimes|nullable|string|max:255',
                'address' => 'sometimes|nullable|string|max:255',
            ]);

            $supplier = Supplier::find($id);
            if (!$supplier) {
                return Helpers::sendResponse(404, [], 'Supplier not found.');
            }

            $supplier->update($validated);

            return Helpers::sendResponse(200, $supplier, 'Supplier updated successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }

    // Delete a supplier
    public function destroy($id)
    {
        try {
            $supplier = Supplier::find($id);
            if (!$supplier) {
                return Helpers::sendResponse(404, [], 'Supplier not found.');
            }

            $supplier->delete();

            return Helpers::sendResponse(200, [], 'Supplier deleted successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(500, [], $th->getMessage());
        }
    }
}
