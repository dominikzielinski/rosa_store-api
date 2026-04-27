<?php

declare(strict_types=1);

namespace Modules\Cms\Admin\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Cms\Admin\Requests\AdminFaqUpsertRequest;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Resources\FaqItemResource;

/**
 * @tags [Admin][CMS] FAQ
 */
class AdminFaqController extends ApiController
{
    /**
     * Upsert FAQ item by backoffice_id (URL path).
     *
     * Partial-update friendly: omitted fields keep their current value (PUT
     * semantics here are "merge" not "replace").
     *
     * @response array{data: FaqItemResource, message: string}
     */
    public function upsert(AdminFaqUpsertRequest $request, int $backofficeId): JsonResponse
    {
        $existing = FaqItem::where('backoffice_id', $backofficeId)->first();

        $item = FaqItem::updateOrCreate(
            ['backoffice_id' => $backofficeId],
            [
                'slug' => $request->input('slug', $existing?->slug),
                'question' => $request->input('question', $existing?->question),
                'answer' => $request->input('answer', $existing?->answer),
                'category' => $request->input('category', $existing?->category),
                'sort_order' => $request->has('sortOrder')
                    ? $request->integer('sortOrder')
                    : ($existing?->sort_order ?? 0),
                'active' => $request->has('active')
                    ? $request->boolean('active')
                    : ($existing?->active ?? true),
            ],
        );

        return $this->success(
            new FaqItemResource($item),
            $item->wasRecentlyCreated ? 'FAQ item created.' : 'FAQ item updated.',
            $item->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * Delete FAQ item by backoffice_id. Idempotent — 204 even if not found.
     */
    public function destroy(int $backofficeId): JsonResponse
    {
        FaqItem::where('backoffice_id', $backofficeId)->delete();

        return response()->json([], 204);
    }
}
