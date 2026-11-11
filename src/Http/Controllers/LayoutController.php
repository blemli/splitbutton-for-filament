<?php

namespace Blemli\SplitButton\Http\Controllers;

use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButtonManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Saves the signed-in user's own menu layouts. Registered inside the
 * panel's authenticated route group, so the panel's guard and middleware
 * apply; a user can only ever write their own state.
 */
class LayoutController
{
    public function __construct(protected SplitButtonManager $manager) {}

    public function update(Request $request): JsonResponse
    {
        $key = $this->authorizedKey($request);

        if ($request->boolean('reset')) {
            $this->manager->reset($key);

            return response()->json(['layout' => (new Layout)->toArray()]);
        }

        $data = $request->validate([
            'order' => ['array', 'max:' . Layout::MAX_ENTRIES],
            'order.*' => ['string', 'max:255'],
            'hidden' => ['array', 'max:' . Layout::MAX_ENTRIES],
            'hidden.*' => ['string', 'max:255'],
            'default' => ['nullable', 'string', 'max:255'],
            'remember_last' => ['nullable', 'boolean'],
        ]);

        $layout = Layout::fromArray([
            ...$data,
            // Only the picks themselves move "last used" (see used()).
            'last_used' => $this->manager->layout($key)->lastUsed,
        ]);

        // A hidden entry cannot sit on the button face.
        if (in_array($layout->default, $layout->hidden, true)) {
            $layout->default = null;
        }

        $this->manager->save($key, $layout);

        return response()->json(['layout' => $layout->toArray()]);
    }

    public function used(Request $request): Response
    {
        $key = $this->authorizedKey($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $layout = $this->manager->layout($key);
        $layout->lastUsed = $data['name'];

        $this->manager->save($key, $layout);

        return response()->noContent();
    }

    protected function authorizedKey(Request $request): string
    {
        abort_unless($this->manager->canCustomize(), 403);

        $key = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ])['key'];

        // Keys are namespaced by panel; never write another panel's state.
        abort_unless(str_starts_with($key, $this->manager->panelId() . '::'), 422);

        return $key;
    }
}
