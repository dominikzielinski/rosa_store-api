<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

use App\Http\Traits\EnumHelpers;

enum BillingTypeEnum: string
{
    use EnumHelpers;

    case Individual = 'individual';
    case Company = 'company';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::Individual => ['id' => $this->value, 'name' => 'Osoba prywatna', 'slug' => $this->value],
            self::Company => ['id' => $this->value, 'name' => 'Firma', 'slug' => $this->value],
        };
    }
}
