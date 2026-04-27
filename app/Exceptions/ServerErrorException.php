<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when server-side state (config, external service, data integrity)
 * prevents the request from being fulfilled (maps to HTTP 5xx).
 */
class ServerErrorException extends Exception implements HttpExceptionInterface
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
