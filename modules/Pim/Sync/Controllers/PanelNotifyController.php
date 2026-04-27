<?php

declare(strict_types=1);

namespace Modules\Pim\Sync\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pim\Sync\Jobs\SyncPimEntityJob;

/**
 * Webhook receiver — backoffice signals a PIM change, we enqueue a refresh job
 * that pulls the entity from the read-only feed.
 *
 * @tags [Sync] Panel
 */
class PanelNotifyController extends ApiController
{
    /**
     * @response array{message: string}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string', 'in:package,box,assortment'],
            'id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'string', 'in:created,updated,deleted'],
            'occurredAt' => ['nullable', 'integer'],
        ]);

        // Assortments arrive as separate signals; we resolve them via their parent
        // box (refetch the full box payload, which includes assortments).
        // Backoffice currently doesn't include parent reference in the signal —
        // for now we no-op assortment events and rely on the periodic full sync.
        if ($data['entity'] === 'assortment') {
            return $this->success(message: 'Assortment changes resolved by periodic full sync.');
        }

        SyncPimEntityJob::dispatch($data['entity'], (int) $data['id'], $data['action']);

        return $this->success(message: 'Sync queued.');
    }
}
