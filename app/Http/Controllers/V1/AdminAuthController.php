<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\V1\Admin;
use App\Services\Helpers;

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


}
