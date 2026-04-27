<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

final readonly class P24RegisterParams
{
    public function __construct(
        public string $sessionId,    // unique per transaction (= our order_number)
        public int $amountGrosze,
        public string $description,
        public string $email,
        public string $urlReturn,    // where P24 sends the user back after payment
        public string $urlStatus,    // IPN endpoint
    ) {}
}
