<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\V1\Storekeeper;
use App\Services\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StorekeeperAuthController extends Controller
{
    public function login(Request $request)
    {
        $response = [];
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $storekeeper = Storekeeper::where('email', $request->email)->first();

        if ($storekeeper && Hash::check($request->password, $storekeeper->password)) {
            $storekeeper->tokens()->delete();
            $newToken = $storekeeper->createToken('storekeeper', ['storekeeper']);
            $token = $newToken->accessToken;
            $token->expires_at = now()->addMinutes(config('sanctum.expiration'));
            $token->save();
            $storekeeper->token = $newToken->plainTextToken;
            $storekeeper = $storekeeper->load('store');
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
        $userId = $request->user()->id;
        UserToken::where('user_id', $userId)
            ->where('user_role', 'storekeeper')
            ->delete();
        return Helpers::sendResponse(200, [], "Logged out successfully");
    }

    public function updateProfilePic(Request $request)
    {
        try {
            $this->validate($request, [
                'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return Helpers::sendResponse(422, [], $e->errors()->first());
        }

        $storekeeper = auth()->user();

        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                if ($storekeeper->image) {
                    $deleted = Storage::disk('public')->delete($storekeeper->image);
                }
                $userId = str_pad($storekeeper->id, 2, '0', STR_PAD_LEFT);
                $destination = "storekeepers/images/$userId";

                $newPath = Helpers::uploadFile($file, $destination);
                $storekeeper->image = $newPath;
                $storekeeper->save();

                DB::commit();
                return Helpers::sendResponse(200, [
                    'image_url' => $storekeeper->image_url
                ], 'Profile picture updated successfully');
            }
            return Helpers::sendResponse(400, [], 'No image uploaded');

        } catch (\Exception $e) {
            DB::rollBack();
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
}
