<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Category;
use Illuminate\Http\Request;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $categories = Category::orderByDesc('id')->get();
            return Helpers::sendResponse(200, $categories);
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], "Failed to fetch categories");
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
                'category_id' => 'required|unique:categories,category_id|max:255',
                'name' => 'required|max:255',
                'description' => 'nullable|string',
            ]);
            $category = new Category();
            $category->name = $request->name;
            $category->category_id = $request->category_id;
            $category->description = $request->description;
            $category->save();

            return Helpers::sendResponse(200, $category);
        } catch (\Exception $e) {
            \Log::info($e->getMessage());
            return Helpers::sendResponse(500, [], "Failed to store the category");
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
            $category = Category::findOrFail($id);
            $category->name = $request->name;
            $category->description = $request->description;
            $category->save();
            return Helpers::sendResponse(200, $category, );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Category not found',
            );
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], "Failed to fetch the category");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\V1\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Category deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Category not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: "Failed to delete the category",
            );
        }
    }

}
