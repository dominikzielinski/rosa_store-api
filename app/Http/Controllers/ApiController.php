<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

/**
 * Base controller for API endpoints. Provides unified JSON response helpers
 * so all controllers return a consistent shape.
 */
abstract class ApiController extends Controller
{
    /**
     * Wrap a resource/collection/array payload into a uniform { data, message } response.
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        $payload = [];

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            $payload['data'] = $data->resolve();
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * Shortcut for the most common 201 Created response.
     */
    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }
}
