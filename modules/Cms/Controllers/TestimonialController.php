<?php

declare(strict_types=1);

namespace Modules\Cms\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Repositories\Interfaces\TestimonialRepositoryInterface;
use Modules\Cms\Resources\TestimonialResource;

/**
 * @tags [CMS] Testimonials
 */
class TestimonialController extends ApiController
{
    public function __construct(
        protected readonly TestimonialRepositoryInterface $repository,
    ) {}

    /**
     * List all active testimonials, ordered.
     *
     *
     * @response array{data: TestimonialResource[]}
     */
    public function index(): JsonResponse
    {
        return $this->success(TestimonialResource::collection($this->repository->getAllActive()));
    }
}
