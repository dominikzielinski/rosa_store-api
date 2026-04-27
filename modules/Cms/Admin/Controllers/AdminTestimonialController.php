<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Admin\Requests\AdminTestimonialUpsertRequest;
use Modules\Cms\Models\Testimonial;
use Modules\Cms\Resources\TestimonialResource;

/**
 * @tags [Admin][CMS] Testimonials
 */
class AdminTestimonialController extends ApiController
{
    /**
     * Upsert testimonial by backoffice_id (URL path).
     *
     * Partial-update friendly: omitted fields keep their current value.
     *
     * @response array{data: TestimonialResource, message: string}
     */
    public function upsert(AdminTestimonialUpsertRequest $request, int $backofficeId): JsonResponse
    {
        $existing = Testimonial::where('backoffice_id', $backofficeId)->first();

        $item = Testimonial::updateOrCreate(
            ['backoffice_id' => $backofficeId],
            [
                'author_name' => $request->input('authorName', $existing?->author_name),
                'author_note' => $request->input('authorNote', $existing?->author_note),
                'content' => $request->input('content', $existing?->content),
                'rating' => $request->input('rating', $existing?->rating),
                'source' => $request->input('source', $existing?->source),
                'posted_at' => $request->input('postedAt', $existing?->posted_at?->toDateString()),
                'sort_order' => $request->has('sortOrder')
                    ? $request->integer('sortOrder')
                    : ($existing?->sort_order ?? 0),
                'active' => $request->has('active')
                    ? $request->boolean('active')
                    : ($existing?->active ?? true),
            ],
        );

        return $this->success(
            new TestimonialResource($item),
            $item->wasRecentlyCreated ? 'Testimonial created.' : 'Testimonial updated.',
            $item->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * Delete testimonial by backoffice_id. Idempotent — 204 even if not found.
     */
    public function destroy(int $backofficeId): JsonResponse
    {
        Testimonial::where('backoffice_id', $backofficeId)->delete();

        return response()->json([], 204);
    }
}
