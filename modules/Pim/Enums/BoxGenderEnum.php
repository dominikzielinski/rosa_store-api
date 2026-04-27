<?php

declare(strict_types=1);

namespace Modules\Pim\Enums;

use App\Http\Traits\EnumHelpers;

enum BoxGenderEnum: string
{
    use EnumHelpers;

    case Women = 'women';
    case Men = 'men';

    /**
     * @return array{id: string, name: string, slug: string}
     */
    public function getData(): array
    {
        return match ($this) {
            self::Women => [
                'id' => $this->value,
                'name' => 'Dla Niej',
                'slug' => $this->value,
            ],
            self::Men => [
                'id' => $this->value,
                'name' => 'Dla Niego',
                'slug' => $this->value,
            ],
        };
    }
}
