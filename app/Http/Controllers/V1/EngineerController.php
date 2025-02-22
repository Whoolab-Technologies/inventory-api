<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\Engineer;
use Illuminate\Support\Facades\Hash;

class EngineerController extends Controller
{

    public function index()
    {
        $engineers = Engineer::with('stores')->get();
        return Helpers::sendResponse(
            status: 200,
            data: $engineers,
            messages: '',
        );
    }

    public function store(Request $request)
    {
        \Log::info("store engineer");
        \Log::info($request->all());
        if (isset($request->store_id)) {
            $request->merge(['store_ids' => [$request->store_id]]);
        }
        $validated = $this->validate($request, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string',
            'email' => 'required|string|email|max:255|unique:engineers',
            'password' => 'required|string|min:6',
            'store_ids' => 'required|array',
            'store_ids.*' => 'exists:stores,id',
        ]);
        \Log::info($request->first_name);

        $engineer = Engineer::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $engineer->stores()->attach($validated['store_ids']);

        // $token = $engineer->createToken('storekeeper-token', ['storekeeper'])->plainTextToken;
        // $response = [
        //     'user' => $engineer,
        //     'token' => $token,
        // ];
        return Helpers::sendResponse(
            status: 200,
            data: $engineer->load('stores'),
            messages: 'Shopkeeper registered successfully',
        );
    }


    public function show($id)
    {
        try {
            $engineer = Engineer::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $engineer,
                messages: '',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
            );
        } catch (\Exception $th) {
            \Log::info(400);
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
            \Log::info("id $id");
            $engineer = Engineer::findOrFail($id);
            $this->validate($request, [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'nullable|string',
                'email' => "sometimes|required|string|email|max:255|unique:engineers,email,{$id}",
                'password' => 'nullable|string|min:6',
            ]);


            if ($request->has('password')) {
                $request->merge(['password' => Hash::make($request->password)]);
            }
            $engineer->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $engineer,
                messages: 'Engineer details updated successfully',
            );

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
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
            $engineer = Engineer::findOrFail($id);
            $engineer->delete();
            return Helpers::sendResponse(
                status: 200,
                data: [],
                messages: 'Engineer deleted successfully',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Engineer not found',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                status: 400,
                data: [],
                messages: $th->getMessage(),
            );
        }
    }

    function getProducts()
    {
        try {

            $user = auth()->user();

            if (!$user->tokenCan('engineer')) {
                return Helpers::sendResponse(403, [], 'Access denied', );
            }
            $stores = $user->load("stores.products");
            return Helpers::sendResponse(
                status: 200,
                data: $stores,
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
