<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Modules\Contact\DataTransferObjects\ContactSubmissionStoreDto;
use Modules\Contact\Mail\ClientConfirmationMail;
use Modules\Contact\Mail\TeamNotificationMail;
use Modules\Contact\Models\ContactSubmission;
use Modules\Contact\Repositories\Interfaces\ContactSubmissionRepositoryInterface;
use Throwable;

readonly class ContactSubmissionService
{
    public function __construct(
        protected ContactSubmissionRepositoryInterface $repository,
        protected Mailer $mailer,
    ) {}

    /**
     * Persist the submission and fire off (queued) notification mails.
     *
     * No DB transaction — this is a single insert and the mail dispatch
     * happens after the row is saved.
     */
    public function store(ContactSubmissionStoreDto $dto): ContactSubmission
    {
        $submission = $this->repository->create($dto);

        $this->dispatchMails($submission);

        return $submission;
    }

    /**
     * Fire the notification mails. Queueing errors are logged but don't fail
     * the user-facing request — the submission is already saved and ops can
     * inspect Laravel's queue / logs.
     */
    private function dispatchMails(ContactSubmission $submission): void
    {
        $teamEmail = config('contact.notification_email');

        try {
            if (is_string($teamEmail) && $teamEmail !== '') {
                $this->mailer->to($teamEmail)->queue(new TeamNotificationMail($submission));
            }

            $this->mailer->to($submission->email)->queue(new ClientConfirmationMail($submission));
        } catch (Throwable $e) {
            Log::error('Failed to queue contact mails', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
