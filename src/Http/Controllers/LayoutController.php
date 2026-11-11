<?php

namespace Blemli\SplitButton\Http\Controllers;

use Blemli\SplitButton\Enums\AutoPin;
use Blemli\SplitButton\Enums\Customization;
use Blemli\SplitButton\Events\SplitButtonCustomized;
use Blemli\SplitButton\Events\SplitButtonReset;
use Blemli\SplitButton\Layout;
use Blemli\SplitButton\SplitButtonManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
            $previous = $this->manager->layout($key);
            $this->manager->reset($key);

            SplitButtonReset::dispatch($this->manager->user(), $key, $previous);

            return response()->json(['layout' => (new Layout)->toArray()]);
        }

        $data = $request->validate([
            'order' => ['array', 'max:' . Layout::MAX_ENTRIES],
            'order.*' => ['string', 'max:255'],
            'hidden' => ['array', 'max:' . Layout::MAX_ENTRIES],
            'hidden.*' => ['string', 'max:255'],
            'default' => ['nullable', 'string', 'max:255'],
            'auto_pin' => ['nullable', Rule::in([Layout::OFF, ...array_column(AutoPin::cases(), 'value')])],
            // What the user just did, for whoever listens to SplitButtonCustomized.
            'change' => ['nullable', Rule::enum(Customization::class)],
            'entry' => ['nullable', 'string', 'max:255'],
        ]);

        $stored = $this->manager->layout($key);

        $layout = Layout::fromArray([
            ...$data,
            // No switch on this button (none offered, or one forced): the
            // user's earlier choice stays for the day there is one again.
            'auto_pin' => $data['auto_pin'] ?? $stored->autoPin,
            // Only the picks themselves count as picks (see used()).
            'last_used' => $stored->lastUsed,
            'usage' => $stored->usage,
        ]);

        // A hidden entry cannot sit on the button face.
        if (in_array($layout->default, $layout->hidden, true)) {
            $layout->default = null;
        }

        $this->manager->save($key, $layout);

        SplitButtonCustomized::dispatch(
            $this->manager->user(),
            $key,
            $layout,
            $stored,
            Customization::tryFrom($data['change'] ?? ''),
            $data['entry'] ?? null,
        );

        return response()->json(['layout' => $layout->toArray()]);
    }

    public function used(Request $request): Response
    {
        $key = $this->authorizedKey($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->manager->recordUse($key, $data['name']);

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
