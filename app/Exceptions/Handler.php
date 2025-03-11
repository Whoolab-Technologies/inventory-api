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


class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
        InvalidArgumentException::class
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        $this->renderable(function (Throwable $e, Request $request) {
            \Log::info(json_encode($e->getMessage()));
            //  \Log::info("Exception caught: " . get_class($e) . ", " . $e->getMessage());
            if ($e instanceof InvalidArgumentException) {
                return Helpers::response(["statusCode" => 400, "message" => 'Invalid argument provided.']);
            }
            if ($e instanceof AuthenticationException) {
                return Helpers::response(["statusCode" => 401, "message" => $e->getMessage()]);
            }
            if ($e instanceof ValidationException) {
                \Log::info(json_encode($e->errors()));

                $errorbag = $e->errors();
                $errors = reset($errorbag);
                $message = $errors && sizeof($errors) > 0 ? $errors[0] : "Some fields are reqired";
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
                \Log::error('Database query error: ' . $e->getMessage());

                // Get the SQL error code
                $errorCode = $e->getCode();

                // Handle specific error codes or provide a general response
                switch ($errorCode) {
                    case '42S02':
                        // Table not found
                        $message = 'Database table not found.';
                    case '23000':
                        // Integrity constraint violation (e.g., foreign key constraint fails)
                        $message = 'Integrity constraint violation.';
                    default:
                        // General database query error
                        $message = "Database query error.";
                }
                return Helpers::response(["statusCode" => 500, "message" => $message]);
            }

            if ($e instanceof BadRouteException) {
                return Helpers::response(["statusCode" => 400, "message" => 'Bad route error.']);
            }

            if ($e instanceof ModelNotFoundException) {
                return Helpers::response(["statusCode" => 404, "message" => 'Model not found.']);
            }

            return parent::render($request, $e);
        });
    }
}
