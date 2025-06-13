<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Brand;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\ModelNotFoundException;
class BrandController extends Controller
{

    public function index()
    {
        try {
            $brands = Brand::orderByDesc('id')->get();
            return Helpers::sendResponse(
                status: 200,
                data: $brands,
                messages: 'Brands retrieved successfully',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(
                status: 500,
                data: [],
                messages: 'Failed to retrieve brands',
            );
        }
    }

    public function show($id)
    {
        try {
            $brand = Brand::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $brand,
                messages: 'Brand retrieved successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Brand not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(
                status: 500,
                data: [],
                messages: 'Failed to retrieve brand',
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);
            $brand = Brand::create($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $brand,
                messages: 'Brand created successfully',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(
                status: 500,
                data: [],
                messages: 'Failed to store brand',
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $this->validate($request, [
                'name' => 'sometimes|string|required|max:255',
                'description' => 'nullable|string',
            ]);
            $brand = Brand::findOrFail($id);
            $brand->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $brand,
                messages: 'Brand updated successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Brand not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(
                status: 500,
                data: [],
                messages: 'Failed to update brand',
            );
        }
    }

    public function destroy($id)
    {
        try {
            $brand = Brand::findOrFail($id);
            $brand->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Brand deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Brand not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(
                status: 500,
                data: [],
                messages: 'Failed to delete brand',
            );
        }
    }
}
