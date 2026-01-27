<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Exception\BadRouteException;
use Illuminate\Validation\ValidationException;
use BadMethodCallException;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\Helpers;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
class Handler extends ExceptionHandler
{
    /**
     * The list of inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
        ValidationException::class,
        InvalidArgumentException::class,
        QueryException::class
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Log::error("Exception Reported: " . $e->getMessage(), ['exception' => $e]);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            Log::info("Exception caught: " . get_class($e) . ", " . $e->getMessage());


            if ($e instanceof InvalidArgumentException) {
                return Helpers::response(["statusCode" => 400, "message" => 'Invalid argument provided.']);
            }

            if ($e instanceof AuthenticationException) {
                return Helpers::response(["statusCode" => 401, "message" => $e->getMessage()]);
            }

            if ($e instanceof ValidationException) {
                Log::info("Validation Errors: " . json_encode($e->errors()));

                $errors = $e->errors();
                $firstError = reset($errors);
                $message = $firstError[0] ?? "Some fields are required";
                return Helpers::response(["statusCode" => 422, "message" => $message]);
            }

            if ($e instanceof NotFoundHttpException) {
                return Helpers::response(["statusCode" => 404, "message" => 'Route not found.']);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return Helpers::response(["statusCode" => 405, "message" => 'Method not allowed for this route.']);
            }

            if ($e instanceof BadMethodCallException) {
                return Helpers::response(["statusCode" => 405, "message" => 'Bad method call.']);
            }

            if ($e instanceof QueryException) {
                Log::error('Database Query Error: ' . $e->getMessage());
                // Handle specific SQL error codes
                $errorCode = $e->getCode();
                $message = match ($errorCode) {
                    '42S02' => 'Database table not found.',
                    '23000' => 'Integrity constraint violation.',
                    default => 'Database query error.',
                };

                return Helpers::response(["statusCode" => 500, "message" => $message]);
            }

            if ($e instanceof BadRouteException) {
                return Helpers::response(["statusCode" => 400, "message" => 'Bad route error.']);
            }

            if ($e instanceof ModelNotFoundException) {
                return Helpers::response(["statusCode" => 404, "message" => 'Model not found.']);
            }

            // Handle all other exceptions gracefully
            Log::error("Unhandled Exception: " . $e->getMessage(), ['exception' => $e]);
            return Helpers::response(["statusCode" => 500, "message" => 'An unexpected error occurred.']);
        });
    }
}
