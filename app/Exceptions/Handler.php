<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Branded error pages for common HTTP statuses (404, 403, …).
        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson() || ! $e instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $e->getStatusCode();

            // Keep Laravel debug page for unexpected 500s while developing.
            if ($status === 500 && config('app.debug')) {
                return null;
            }

            if (view()->exists("errors.$status")) {
                return response()->view("errors.$status", [], $status);
            }

            return null;
        });
    }
}
