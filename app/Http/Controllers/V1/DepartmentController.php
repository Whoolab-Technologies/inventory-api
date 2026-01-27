<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Department;
use Illuminate\Http\Request;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $departments = Department::withCount('engineers')->orderByDesc('id')->get();
            return Helpers::sendResponse(200, $departments, );

        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], "Failed to fetch departments");
        }
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|max:255',
                'description' => 'nullable|string',
            ]);
            $department = new Department();
            $department->name = $request->name;
            $department->description = $request->description;
            $department->save();
            $department = Department::withCount('engineers')->find($department->id);
            return Helpers::sendResponse(200, $department, );
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], "Failed to store department");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $this->validate($request, [
                'name' => 'sometimes|required|max:255',
                'description' => 'nullable|string',
            ]);
            $department = Department::findOrFail($id);
            $department->name = $request->name;
            $department->description = $request->description;
            $department->save();
            $department = Department::withCount('engineers')->find($department->id);
            return Helpers::sendResponse(200, $department, );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Department not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], "Failed to update department");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\V1\Department  $department
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Department deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Department not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: "Failed to delete department",
            );
        }
    }
}
