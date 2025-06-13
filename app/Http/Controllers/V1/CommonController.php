<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\Admin;
use App\Models\V1\Engineer;
use App\Models\V1\Storekeeper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetLink;
use Illuminate\Support\Facades\Crypt;

class CommonController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'user_type' => 'required|in:admin,engineer,storekeeper',
            ]);

            $provider = match ($request->user_type) {
                'admin' => Admin::class,
                'engineer' => Engineer::class,
                'storekeeper' => Storekeeper::class,
            };

            $user = $provider::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            $token = Str::random(60);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            // Send reset email
            Mail::to($user->email)->send(new PasswordResetLink($user->email, $token, $request->user_type));

            return Helpers::sendResponse(
                200,
                [],
                'Password reset link sent successfully.',
            );
        } catch (\Throwable $th) {
            return Helpers::sendResponse(
                400,
                [],
                $th->getMessage(),
            );
        }
    }


    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'data' => 'required',
                'email' => 'required|email',
                'password' => 'required|confirmed|min:6',
            ]);

            $decoded = json_decode(Crypt::decryptString($request->data), true);

            // Sanity check
            if ($request->email !== $decoded['email']) {
                return Helpers::sendResponse(400, [], "Email does not match token");

            }

            $email = $decoded['email'];
            $token = $decoded['token'];
            $userType = $decoded['user_type'];

            $record = DB::table('password_reset_tokens')->where('email', $email)->first();

            if (
                !$record ||
                !Hash::check($token, $record->token) ||
                now()->diffInMinutes($record->created_at) > 30
            ) {
                return Helpers::sendResponse(400, [], "Invalid or expired token.");

            }

            $model = match ($userType) {
                'admin' => Admin::class,
                'engineer' => Engineer::class,
                'storekeeper' => Storekeeper::class,
                default => null,
            };

            if (!$model) {
                return response()->json(['message' => 'Invalid user type.'], 400);
            }

            $user = $model::where('email', $email)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            $user->password = bcrypt($request->password);
            $user->save();

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return Helpers::sendResponse(200, $userType, 'Password reset successful.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(400, [], $th->getMessage());
        }
    }

}