<?php

declare(strict_types=1);

namespace Modules\Contact\Repositories;

use Modules\Contact\DataTransferObjects\ContactSubmissionStoreDto;
use Modules\Contact\Models\ContactSubmission;
use Modules\Contact\Repositories\Interfaces\ContactSubmissionRepositoryInterface;

readonly class ContactSubmissionRepository implements ContactSubmissionRepositoryInterface
{
    public function __construct(
        protected ContactSubmission $model,
    ) {}

    public function create(ContactSubmissionStoreDto $dto): ContactSubmission
    {
        return $this->model->create([
            'type' => $dto->type,
            'full_name' => $dto->fullName,
            'email' => $dto->email,
            'message' => $dto->message,
            'phone' => $dto->phone,
            'company' => $dto->company,
            'nip' => $dto->nip,
            'event_type' => $dto->eventType,
            'gift_count' => $dto->giftCount,
            'preferred_contact' => $dto->preferredContact,
            'consent_data' => $dto->consentData,
            'consent_marketing' => $dto->consentMarketing,
            'ip_address' => $dto->ipAddress,
            'user_agent' => $dto->userAgent,
        ]);
    }
}
