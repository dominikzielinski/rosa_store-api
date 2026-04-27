<?php

declare(strict_types=1);

namespace Modules\Contact\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contact\databases\factories\ContactSubmissionFactory;
use Modules\Contact\Enums\ContactSubmissionTypeEnum;
use Modules\Contact\Enums\PreferredContactEnum;

/**
 * @property int $id
 * @property ContactSubmissionTypeEnum $type
 * @property string $full_name
 * @property string $email
 * @property string $message
 * @property string|null $phone
 * @property string|null $company
 * @property string|null $nip
 * @property string|null $event_type
 * @property string|null $gift_count
 * @property PreferredContactEnum|null $preferred_contact
 * @property bool $consent_data
 * @property bool $consent_marketing
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ContactSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'full_name',
        'email',
        'message',
        'phone',
        'company',
        'nip',
        'event_type',
        'gift_count',
        'preferred_contact',
        'consent_data',
        'consent_marketing',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactSubmissionTypeEnum::class,
            'preferred_contact' => PreferredContactEnum::class,
            'consent_data' => 'boolean',
            'consent_marketing' => 'boolean',
            'created_at' => 'datetime:U',
            'updated_at' => 'datetime:U',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ContactSubmissionFactory::new();
    }
}
