<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
     */
    public function register(): void
    {
        $this->renderable(function (PmsBusinessRuleException $exception, Request $request) {
            if (! $this->isPmsRequest($request)) return null;
            $feedback = ['heading' => 'Action not available', 'message' => $exception->getMessage(),
                'details' => $exception->details, 'next' => 'Review the ticket stage and try the appropriate next step.'];
            if (! $request->expectsJson()) return back()->withInput()->with('pms_feedback', $feedback);
            $payload = ['success' => false, 'code' => $exception->error,
                'error' => $exception->error, 'message' => $exception->getMessage(),
                'details' => $exception->details, 'errors' => (object) [], 'data' => null];
            if ($request->is('api/v1/project-management/*')) {
                $payload['request_id'] = $request->attributes->get('request_id') ?: (string) \Illuminate\Support\Str::uuid();
                $payload['timestamp'] = now()->toIso8601String();
            }
            return response()->json($payload, $exception->status);
        });

        $this->renderable(function (Throwable $exception, Request $request) {
            if (! $this->isPmsRequest($request) || $request->is('api/v1/project-management/*')
                || $exception instanceof PmsBusinessRuleException) return null;
            $status = match (true) {
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $approvalRequired = $exception instanceof ValidationException && array_key_exists('reviewer', $exception->errors());
            $code = match ($status) {
                401 => 'UNAUTHENTICATED', 403 => 'PERMISSION_DENIED', 404 => 'RESOURCE_NOT_FOUND',
                409 => 'CONFLICT', 422 => $approvalRequired ? 'APPROVAL_REQUIRED' : 'VALIDATION_ERROR', default => 'INTERNAL_ERROR',
            };
            $message = match ($status) {
                401 => 'Sign in to continue.',
                403 => str_contains($request->route()?->getActionName() ?? '', '\\Pms\\CycleController@')
                    || str_contains($request->route()?->getActionName() ?? '', '\\Pms\\GlobalCycleController@')
                    ? 'Only Project Managers and Product Owners can manage planning cycles.'
                    : 'You do not have permission to perform this action. Contact a Project Manager or administrator if you need access.',
                404 => 'This item is unavailable or outside your project access.',
                409 => 'This record changed. Refresh the page and try again.',
                422 => $exception instanceof ValidationException
                    ? (collect($exception->errors())->flatten()->first() ?: 'Review the highlighted fields.')
                    : 'Review the submitted information and try again.',
                default => 'The request could not be completed. Please try again later.',
            };
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'code' => $code, 'error' => $code,
                    'message' => $message, 'details' => (object) [],
                    'errors' => $exception instanceof ValidationException ? $exception->errors() : (object) [],
                    'data' => null], $status);
            }
            if ($exception instanceof ValidationException) {
                if ($approvalRequired) {
                    $ticket = $request->route('ticket');
                    $project = $ticket instanceof \App\Models\PmWorkItem ? $ticket->project : null;
                    return back()->withInput()->withErrors($exception->errors())->with('pms_feedback', [
                        'heading' => 'Project Manager review required', 'message' => $message,
                        'details' => [], 'next' => 'Assign an active Project Manager to the project, then submit again.',
                        'action_url' => $project ? route('pms.projects.show', $project) : route('pms.change-reviews.index'),
                        'action_label' => $project ? 'Open project' : 'View reviews',
                    ]);
                }
                return null;
            }
            if (in_array($status, [403, 409, 422, 500], true)) {
                return response()->view('pms.action-error', ['heading' => $status === 403 ? 'Permission required' : 'Action unavailable',
                    'message' => $message, 'status' => $status], $status);
            }
            return null;
        });

        $this->renderable(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/project-management/*')) return null;

            $status = match (true) {
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                400 => 'BAD_REQUEST', 401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN',
                404 => str_contains($request->path(), 'Project') ? 'PROJECT_NOT_FOUND' : 'RESOURCE_NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED', 409 => 'CONFLICT', 422 => 'VALIDATION_ERROR',
                429 => 'RATE_LIMITED', default => 'INTERNAL_ERROR',
            };
            $message = match ($status) {
                401 => 'A valid PMS bearer token is required.',
                403 => 'You do not have permission to perform this action.',
                404 => 'The requested resource does not exist.',
                422 => 'Request validation failed.',
                429 => 'Rate limit exceeded.',
                500 => 'The request could not be completed.',
                default => $exception->getMessage() ?: 'The request could not be completed.',
            };
            $requestId = $request->attributes->get('request_id') ?: (string) \Illuminate\Support\Str::uuid();
            return response()->json([
                'success' => false, 'code' => $code, 'message' => $message, 'data' => null,
                'errors' => $exception instanceof ValidationException ? $exception->errors() : (object) [],
                'request_id' => $requestId, 'timestamp' => now()->toIso8601String(),
            ], $status)->header('X-Request-Id', $requestId);
        });

        $this->renderable(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'The submitted data is invalid.',
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $this->renderable(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => (object) [],
            ], 401);
        });

        $this->renderable(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
                'errors' => (object) [],
            ], 403);
        });

        $this->renderable(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            $message = $exception->getMessage();
            if ($message === '') {
                $message = match ($exception->getStatusCode()) {
                    403 => 'You are not authorized to perform this action.',
                    404 => 'The requested record was not found.',
                    423 => 'This feature is currently locked for your account.',
                    default => 'The request could not be completed.',
                };
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) [],
            ], $exception->getStatusCode());
        });

        $this->renderable(function (Throwable $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The request could not be completed.',
                'errors' => (object) [],
            ], 500);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }

    private function isPmsRequest(Request $request): bool
    {
        return $request->is('backoffice/project-management/*')
            || $request->is('api/backoffice/pms/*')
            || $request->is('api/v1/project-management/*');
    }
}
