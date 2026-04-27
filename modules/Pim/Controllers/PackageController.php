<?php

declare(strict_types=1);

namespace Modules\Pim\Controllers;

use App\Exceptions\ClientErrorException;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Pim\Repositories\Interfaces\PackageRepositoryInterface;
use Modules\Pim\Resources\PackageResource;

/**
 * @tags [Pim] Package
 */
class PackageController extends ApiController
{
    public function __construct(
        protected readonly PackageRepositoryInterface $packageRepository,
    ) {}

    /**
     * List all active packages with their boxes and images.
     *
     * Public endpoint powering the storefront's home page and package subpages.
     * Data is maintained by the backoffice app — this endpoint just reads from
     * the local replica that is hydrated by `pim:sync-full` + webhook signals.
     *
     * @response array{data: PackageResource[]}
     */
    public function index(): JsonResponse
    {
        $packages = $this->packageRepository->getAllActive();

        return $this->success(PackageResource::collection($packages));
    }

    /**
     * Get a single package by slug (standard/premium/vip) with its active boxes.
     *
     * @throws ClientErrorException on unknown / inactive slug (404)
     *
     * @response array{data: PackageResource}
     */
    public function show(string $slug): JsonResponse
    {
        $package = $this->packageRepository->getActiveBySlug($slug);

        return $this->success(new PackageResource($package));
    }
}
