<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\V1\Unit;
use App\Services\Helpers;

class UnitController extends Controller
{


    public function index()
    {
        try {
            $units = Unit::all();
            return Helpers::sendResponse(200, $units, 'Units retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $unit = Unit::findOrFail($id);
            return Helpers::sendResponse(200, $unit, 'Unit retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Unit not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $unit = Unit::create($request->all());
            return Helpers::sendResponse(201, $unit, 'Unit created successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $unit = Unit::findOrFail($id);
            $unit->update($request->all());
            return Helpers::sendResponse(200, $unit, 'Unit updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Unit not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $unit = Unit::findOrFail($id);
            $unit->delete();
            return Helpers::sendResponse(200, [], 'Unit deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return Helpers::sendResponse(404, [], 'Unit not found');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
