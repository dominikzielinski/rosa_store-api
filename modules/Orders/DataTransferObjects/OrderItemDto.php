<?php

declare(strict_types=1);

namespace Modules\Orders\DataTransferObjects;

final readonly class OrderItemDto
{
    public function __construct(
        public int $boxId,
        public int $quantity,
    ) {}

    /**
     * @param  array{boxId: int, quantity: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            boxId: (int) $data['boxId'],
            quantity: (int) $data['quantity'],
        );
    }
}
