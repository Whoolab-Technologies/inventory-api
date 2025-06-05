<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\V1\Admin;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:admins',
                'password' => 'required|string|min:6|confirmed',
            ]);

            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);


            $token = $admin->createToken('admin', ['admin'])->plainTextToken;

            $admin->token = $token;

            // Return success response with the generated token
            return Helpers::sendResponse(
                200,
                $admin,
                'Admin registered successfully',

            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                400,
                [],
                $th->getMessage(),
            );
        }
    }

    public function login(Request $request)
    {

        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $admin = Admin::where('email', $request->email)->first();

            if ($admin && Hash::check($request->password, $admin->password)) {
                // $admin->tokens()->delete();
                $newToken = $admin->createToken('admin', ['admin']);
                $token = $newToken->accessToken;
                $token->expires_at = now()->addMinutes(config('sanctum.expiration'));
                $token->save();
                $admin->token = $newToken->plainTextToken;
                return Helpers::sendResponse(200, $admin);
            }
            return Helpers::sendResponse(status: 401, messages: 'Invalid credentials', );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(status: 400, messages: $th->getMessage(), );
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return Helpers::sendResponse(200, [], "Logged out successfully");
    }

    public function index()
    {
        $user = auth()->user();
        $admins = Admin::where('id', '!=', $user->id)->get();
        return Helpers::sendResponse(
            status: 200,
            data: $admins,
            messages: '',
        );
    }

    public function show($id)
    {
        try {

            $admin = Admin::findOrFail($id);
            return Helpers::sendResponse(
                status: 200,
                data: $admin,
                messages: '',
            );
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Admin not found',
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
            $admin = Admin::findOrFail($id);
            $this->validate($request, [
                'name' => 'sometimes|required|string|max:255',
                'email' => "sometimes|required|string|email|max:255|unique:admins,email,{$id}",
                'password' => 'nullable|string|min:6',
            ]);


            if ($request->has('password')) {
                $request->merge(['password' => Hash::make($request->password)]);
            }
            $admin->update($request->all());
            return Helpers::sendResponse(
                status: 200,
                data: $admin,
                messages: 'Admin details updated successfully',
            );

        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Admin not found',
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
            $admin = Admin::findOrFail($id);
            $admin->delete();
            return response()->json(['message' => 'Admin deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return Helpers::sendResponse(
                status: 404,
                data: [],
                messages: 'Admin not found',
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
