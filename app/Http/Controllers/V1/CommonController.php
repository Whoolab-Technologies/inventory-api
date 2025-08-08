<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use App\Services\V1\NotificationService;
use Illuminate\Http\Request;
use App\Services\Helpers;
use App\Models\V1\Admin;
use App\Models\V1\Engineer;
use App\Models\V1\Storekeeper;
use App\Models\V1\Product;
use App\Models\V1\MaterialRequest;
use \App\Models\V1\UserToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetLink;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class CommonController extends Controller
{

    protected $notificationService;

    public function __construct(
        NotificationService $notificationService,
    ) {
        $this->notificationService = $notificationService;
    }
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

    public function getMaterialRequests(Request $request)
    {
        $search = $request->input('search');
        $statusId = $request->input('status_id');
        $storeId = $request->input('store_id');
        $engineerId = $request->input('engineer_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');   // string (Y-m-d) or null

        try {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            if ($dateFrom) {
                $dateFrom = Carbon::parse($dateFrom)->format('Y-m-d');
            }

            if ($dateTo) {
                $dateTo = Carbon::parse($dateTo)->format('Y-m-d');
            }
            $materialRequests = MaterialRequest::with(['items', 'items.product', 'store', 'engineer'])
                ->search($search, $statusId, $dateFrom, $dateTo, $storeId, $engineerId)
                ->orderByDesc('id')
                ->get();

            return Helpers::sendResponse(200, $materialRequests);
        } catch (\Throwable $th) {
            return Helpers::sendResponse(400, [], $th->getMessage());
        }

    }

    public function saveFcmToken(Request $request)
    {
        $user = auth()->user();

        $role = $user->tokenCan('engineer') ? 'engineer' :
            ($user->tokenCan('storekeeper') ? 'storekeeper' :
                ($user->tokenCan('admin') ? 'admin' : 'unknown'));

        $request->merge([
            'user_role' => $role,
            'user_id' => $user->id,
        ]);
        $request->validate([
            'user_id' => 'required|integer',
            'user_role' => 'required|string|in:admin,engineer,storekeeper',
            'fcm_token' => 'required|string',
            'device_model' => 'nullable|string',
            'device_brand' => 'nullable|string',
            'os_version' => 'nullable|string',
            'platform' => 'nullable|string',
            'device_id' => 'nullable|string',
            'sdk' => 'nullable|string',
        ]);

        try {
            $data = [
                'user_id' => $request->user_id,
                'user_role' => $request->user_role,
                'fcm_token' => $request->fcm_token,
                'device_model' => $request->device_model,
                'device_brand' => $request->device_brand,
                'os_version' => $request->os_version,
                'platform' => $request->platform,
                'device_id' => $request->device_id,
                'sdk' => $request->sdk,
            ];

            // Assuming UserToken is the model for the user_tokens table
            UserToken::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'user_role' => $request->user_role,
                    'device_id' => $request->device_id,
                ],
                $data
            );

            return Helpers::sendResponse(200, [], 'FCM token saved successfully.');
        } catch (\Throwable $th) {
            return Helpers::sendResponse(400, [], $th->getMessage());
        }
    }

    public function removeFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $deleted = UserToken::where('fcm_token', $request->token)->delete();

            if ($deleted) {
                return Helpers::sendResponse(200, [], 'FCM token removed successfully.');
            } else {
                return Helpers::sendResponse(404, [], 'FCM token not found.');
            }
        } catch (\Throwable $th) {
            return Helpers::sendResponse(400, [], $th->getMessage());
        }
    }


    public function testNotification(Request $request)
    {
        try {
            $tokens = $request->tokens;
            $title = $request->title;
            $body = $request->body;
            $data = $request->data;
            $this->notificationService->sendToTokens($tokens, $title, $body, $data);
            return Helpers::sendResponse(200, [], 'Notification dispatched');
        } catch (\Throwable $th) {
            Log::error('Error in testNotification', ['error' => $th->getMessage()]);
            return Helpers::sendResponse(400, [], $th->getMessage());
        }
    }



    public function getProducts(Request $request)
    {
        try {
            $searchTerm = $request->query('search');
            $perPage = $request->query('per_page', 50);

            $products = Product::query();

            if ($searchTerm) {
                $products->search($searchTerm);
            }
            $paginatedProducts = $products->paginate($perPage);
            return Helpers::sendResponse(200, $paginatedProducts->items(), 'Products retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }
    public function getProduct(Request $request, $id)
    {
        try {
            $products = Product::where('id', $id)->get();
            return Helpers::sendResponse(200, $products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return Helpers::sendResponse(500, [], $e->getMessage());
        }
    }

}