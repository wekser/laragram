<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Wekser\Laragram\Broadcasting\PendingBroadcast;
use Wekser\Laragram\Broadcasting\ViewCatalog;

class BroadcastController
{
    /**
     * Show the broadcast composer.
     */
    public function create(): View
    {
        $model = config('laragram.auth.user.model');

        return view('laragram::admin.broadcast', [
            'roles' => $model::query()->select('role')->distinct()->orderBy('role')->pluck('role'),
            'views' => ViewCatalog::all(),
        ]);
    }

    /**
     * Preview (dry-run) or send a broadcast to the filtered audience.
     */
    public function store(Request $request): RedirectResponse
    {
        // Default to 'text' so a form without the toggle (and older callers)
        // behaves exactly as before.
        $contentType = $request->input('content_type') === 'view' ? 'view' : 'text';

        $rules = [
            'role'             => ['nullable', 'string', 'max:50'],
            'include_inactive' => ['sometimes', 'boolean'],
            'action'           => ['required', 'in:preview,send'],
        ];

        if ($contentType === 'view') {
            $rules['view'] = ['required', 'string', Rule::in(ViewCatalog::all())];
            $rules['data'] = ['nullable', 'string'];
        } else {
            $rules['message'] = ['required', 'string', 'max:4096'];
        }

        $data = $request->validate($rules);

        if ($contentType === 'view') {
            $viewData = [];

            if (! empty($data['data'])) {
                $viewData = json_decode($data['data'], true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($viewData)) {
                    return back()->withInput()->withErrors(['data' => 'Data must be a valid JSON object.']);
                }
            }

            /** @var PendingBroadcast $pending */
            $pending = app('laragram.broadcast')->view($data['view'], $viewData);
        } else {
            /** @var PendingBroadcast $pending */
            $pending = app('laragram.broadcast')->text($data['message']);
        }

        if (! empty($data['role'])) {
            $pending->role($data['role']);
        }

        if (! empty($data['include_inactive'])) {
            $pending->includeInactive();
        }

        if ($data['action'] === 'preview') {
            return back()
                ->withInput()
                ->with('status', "Dry run: this broadcast would reach {$pending->count()} user(s).");
        }

        // Guard against blocking the HTTP request: the synchronous path sends to
        // every recipient inline, which for a large audience would exceed
        // max_execution_time and deliver only partially. Above the limit, require
        // the queue (or the CLI command) instead.
        if (! config('laragram.queue.enabled', false)) {
            $limit = (int) config('laragram.broadcast.web_sync_limit', 200);

            if (($count = $pending->count()) > $limit) {
                return back()->withInput()->withErrors([
                    'message' => "Refusing to send synchronously to {$count} users from the web UI (limit {$limit}). "
                        . 'Enable the queue (LARAGRAM_QUEUE_ENABLED=true) or run `php artisan laragram:broadcast`.',
                ]);
            }
        }

        $result = $pending->send();

        $summary = $result->queued > 0
            ? "queued {$result->queued} message(s)"
            : "sent {$result->sent} of {$result->total}";

        return back()->with('status', "Broadcast complete — {$summary}.");
    }
}
