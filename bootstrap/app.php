<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*') || ! $response->isClientError() && ! $response->isServerError()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $body = json_decode($response->getContent(), true);
            $code = match ($status) {
                401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found',
                409 => 'conflict', 422 => 'validation_failed', 429 => 'rate_limited',
                default => 'request_failed',
            };
            $requestId = (string) Str::uuid();
            $error = [
                'code' => $code,
                'message' => $status >= 500 ? 'Layanan mengalami kesalahan.' : ($body['message'] ?? Response::$statusTexts[$status] ?? 'Permintaan gagal.'),
                'request_id' => $requestId,
            ];
            if (isset($body['errors'])) {
                $error['fields'] = $body['errors'];
            }

            $response->headers->set('X-Request-Id', $requestId);
            $response->setContent(json_encode(['error' => $error], JSON_THROW_ON_ERROR));
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        });
    })->create();
