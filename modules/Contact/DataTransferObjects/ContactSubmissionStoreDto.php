<?php

declare(strict_types=1);

namespace Modules\Contact\DataTransferObjects;

use Modules\Contact\Enums\ContactSubmissionTypeEnum;
use Modules\Contact\Enums\PreferredContactEnum;
use Modules\Contact\Requests\ContactSubmissionStoreRequest;

final readonly class ContactSubmissionStoreDto
{
    public function __construct(
        public ContactSubmissionTypeEnum $type,
        public string $fullName,
        public string $email,
        public string $message,
        public ?string $phone,
        public ?string $company,
        public ?string $nip,
        public ?string $eventType,
        public ?string $giftCount,
        public ?PreferredContactEnum $preferredContact,
        public bool $consentData,
        public bool $consentMarketing,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    public static function fromRequest(ContactSubmissionStoreRequest $request): self
    {
        $v = $request->validated();

        return new self(
            type: ContactSubmissionTypeEnum::from($v['type']),
            // Strip CR/LF from fullName — defense in depth against mail header injection
            // (subject of TeamNotificationMail interpolates this value).
            fullName: preg_replace('/[\r\n]+/', ' ', trim((string) $v['fullName'])) ?? '',
            email: mb_strtolower(trim((string) $v['email'])),
            message: trim((string) $v['message']),
            phone: self::normalizePhone($v['phone'] ?? null),
            company: self::nullIfEmpty($v['company'] ?? null),
            nip: self::normalizeNip($v['nip'] ?? null),
            eventType: self::nullIfEmpty($v['eventType'] ?? null),
            giftCount: self::nullIfEmpty($v['giftCount'] ?? null),
            preferredContact: isset($v['preferredContact']) && $v['preferredContact'] !== ''
                ? PreferredContactEnum::from($v['preferredContact'])
                : null,
            consentData: (bool) $v['consentData'],
            consentMarketing: (bool) ($v['consentMarketing'] ?? false),
            ipAddress: $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 500) ?: null,
        );
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function normalizePhone(?string $value): ?string
    {
        $value = self::nullIfEmpty($value);

        return $value !== null ? preg_replace('/\s+/', '', $value) : null;
    }

    private static function normalizeNip(?string $value): ?string
    {
        $value = self::nullIfEmpty($value);

        return $value !== null ? preg_replace('/[\s\-]/', '', $value) : null;
    }
}
