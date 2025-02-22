<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Engineer;
use Illuminate\Http\Request;
use App\Services\Helpers;
use Illuminate\Support\Facades\Hash;
class EngineerAuthController extends Controller
{

    public function login(Request $request)
    {
        \Log::info($request->all());
        $response = [];
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $storekeeper = Engineer::where('email', $request->email)->first();

        if ($storekeeper && Hash::check($request->password, $storekeeper->password)) {
            $token = $storekeeper->createToken('engineer', ['engineer'])->plainTextToken;
            $storekeeper->token = $token;

            return Helpers::sendResponse(
                200,
                $storekeeper,
                'Logged in successfully',
            );
        }
        return Helpers::sendResponse(
            status: 401,
            data: $response,
            messages: 'Invalid credentials',
        );
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return Helpers::sendResponse(200, [], "Logged out successfully");
    }
}
