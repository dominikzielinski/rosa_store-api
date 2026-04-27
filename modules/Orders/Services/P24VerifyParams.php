<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

final readonly class P24VerifyParams
{
    public function __construct(
        public string $sessionId,
        public int $orderId,         // P24's internal transaction id (from webhook)
        public int $amountGrosze,
    ) {}
}
