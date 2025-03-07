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

        $engineer = Engineer::where('email', $request->email)->first();

        if ($engineer && Hash::check($request->password, $engineer->password)) {
            $engineer->tokens()->delete();
            $newToken = $engineer->createToken('engineer', ['engineer']);
            $token = $newToken->accessToken;
            $token->expires_at = now()->addMinutes(config('sanctum.expiration'));
            $token->save();
            $engineer->token = $newToken->plainTextToken;

            return Helpers::sendResponse(
                200,
                $engineer,
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
