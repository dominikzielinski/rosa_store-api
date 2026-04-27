<?php

declare(strict_types=1);

namespace Modules\Contact\Enums;

use App\Http\Traits\EnumHelpers;

enum PreferredContactEnum: string
{
    use EnumHelpers;

    case Email = 'email';
    case Phone = 'phone';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::Email => [
                'id' => $this->value,
                'name' => 'E-mail',
                'slug' => $this->value,
            ],
            self::Phone => [
                'id' => $this->value,
                'name' => 'Telefon',
                'slug' => $this->value,
            ],
        };
    }
}
