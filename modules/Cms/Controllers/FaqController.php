<?php

declare(strict_types=1);

namespace Modules\Cms\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Repositories\Interfaces\FaqRepositoryInterface;
use Modules\Cms\Resources\FaqItemResource;

/**
 * @tags [CMS] FAQ
 */
class FaqController extends ApiController
{
    public function __construct(
        protected readonly FaqRepositoryInterface $repository,
    ) {}

    /**
     * List all active FAQ items, ordered.
     *
     *
     * @response array{data: FaqItemResource[]}
     */
    public function index(): JsonResponse
    {
        return $this->success(FaqItemResource::collection($this->repository->getAllActive()));
    }
}
