<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when a request cannot be fulfilled due to invalid input or
 * violation of a business rule (maps to HTTP 4xx).
 */
class ClientErrorException extends Exception implements HttpExceptionInterface
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = Response::HTTP_BAD_REQUEST,
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
