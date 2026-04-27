<?php

declare(strict_types=1);

namespace Modules\Contact\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Contact\DataTransferObjects\ContactSubmissionStoreDto;
use Modules\Contact\Requests\ContactSubmissionStoreRequest;
use Modules\Contact\Services\ContactSubmissionService;

/**
 * @tags [Contact] Submission
 */
class ContactSubmissionController extends ApiController
{
    public function __construct(
        protected readonly ContactSubmissionService $contactSubmissionService,
    ) {}

    /**
     * Submit a contact form (B2B or retail).
     *
     * Accepts submissions from both `/dla-firm` and `/kontakt` pages — the
     * difference is the `type` field ("b2b" or "retail"). The endpoint is
     * public (no auth) and rate-limited to 5 requests per IP per minute.
     *
     * @throws \Throwable
     *
     * @response array{message: string}
     */
    public function store(ContactSubmissionStoreRequest $request): JsonResponse
    {
        // Honeypot — pretend success without persisting or mailing. Bots get no feedback.
        if ($request->looksLikeBot()) {
            return $this->created(
                [],
                'Dziękujemy! Skontaktujemy się z Tobą najszybciej jak to możliwe.',
            );
        }

        $dto = ContactSubmissionStoreDto::fromRequest($request);
        $this->contactSubmissionService->store($dto);

        return $this->created(
            [],
            'Dziękujemy! Skontaktujemy się z Tobą najszybciej jak to możliwe.',
        );
    }
}
