<?php

declare(strict_types=1);

namespace Modules\Contact\Enums;

use App\Http\Traits\EnumHelpers;

enum ContactSubmissionTypeEnum: string
{
    use EnumHelpers;

    case B2B = 'b2b';
    case Retail = 'retail';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::B2B => [
                'id' => $this->value,
                'name' => 'B2B (firma)',
                'slug' => $this->value,
            ],
            self::Retail => [
                'id' => $this->value,
                'name' => 'Klient indywidualny',
                'slug' => $this->value,
            ],
        };
    }
}
