<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\V1\Storekeeper;
use App\Services\Helpers;

class StorekeeperAuthController extends Controller
{
    public function login(Request $request)
    {
        \Log::info($request->all());
        $response = [];
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $storekeeper = Storekeeper::where('email', $request->email)->first();

        if ($storekeeper && Hash::check($request->password, $storekeeper->password)) {
            $token = $storekeeper->createToken('storekeeper-token', ['storekeeper'])->plainTextToken;
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
