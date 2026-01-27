<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Helpers
{
    public static function download($filePath, $delete = true): BinaryFileResponse
    {
        return response()->download($filePath)->deleteFileAfterSend($delete);
    }

    public static function response(array $args): JsonResponse
    {
        $statusCode = $args['statusCode'] ?? 400;
        $message = $args['message'] ?? 'Something went wrong';
        $isError = $statusCode >= 400 && $statusCode < 600;
        $data = $args['data'] ?? [];

        $response = [
            'status' => $statusCode,
            'message' => $message,
            'error' => $isError ? true : false,
            'data' => $data,
        ];

        return response()->json($response, $statusCode, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    static public function uploadFile($file, $destination)
    {
        $fileName = uniqid() . '_' . $file->getClientOriginalName();

        $path = $file->storeAs($destination, $fileName);
        return $path;
    }

    static public function sendEmail($template, $data, $to, $subject)
    {
        Mail::send($template, $data, function ($message) use ($subject, $to) {
            $message->to($to)->subject($subject);
            $message->from(env("MAIL_USERNAME"), env("MAIL_FROM_NAME"));
        });
    }

    static public function sendPasswordResetLink($data, $to)
    {
        return self::sendEmail("mail", $data, $to, "Reset Password Request");
    }

    static public function generateRandomString($length = 6)
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    static public function sendResponse($status = 400, $data = null, $messages = "")
    {
        return Helpers::response(["statusCode" => $status, "data" => $data, "message" => $messages]);
    }
}
