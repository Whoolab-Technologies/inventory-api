<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\Status;
use Illuminate\Http\Request;
use App\Services\Helpers;

class SettingsController extends Controller
{
    public function index(Request $request)
    {

        try {
            $response = [];
            $statuses = Status::get();
            $response['statuses'] = $statuses;
            return Helpers::sendResponse(
                status: 200,
                data: $response,
                messages: '',
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
