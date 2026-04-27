<?php

declare(strict_types=1);

namespace Modules\Contact\Repositories\Interfaces;

use Modules\Contact\DataTransferObjects\ContactSubmissionStoreDto;
use Modules\Contact\Models\ContactSubmission;

interface ContactSubmissionRepositoryInterface
{
    public function create(ContactSubmissionStoreDto $dto): ContactSubmission;
}
