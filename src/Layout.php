<?php

namespace Blemli\SplitButton;

use Blemli\SplitButton\Enums\AutoPin;

/**
 * One user's arrangement of one split button: the order of its entries,
 * the ones they hid, the one they put on the button face, whether the
 * button follows their picks instead, and the picks themselves.
 */
final class Layout
{
    public const MAX_ENTRIES = 100;

    /** The user switched "follow my picks" off, whatever the developer's default. */
    public const OFF = 'off';

    /**
     * @param  array<string>  $order
     * @param  array<string>  $hidden
     * @param  string | null  $autoPin  an AutoPin value, OFF, or null for the developer's default
     * @param  array<string, array{count: int, score: float, at: int}>  $usage  picks per entry: how many, their faded sum, and when last
     */
    public function __construct(
        public array $order = [],
        public array $hidden = [],
        public ?string $default = null,
        public ?string $lastUsed = null,
        public ?string $autoPin = null,
        public array $usage = [],
    ) {}

    /**
     * @param  array<string, mixed> | null  $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $autoPin = $data['auto_pin'] ?? null;

        return new self(
            order: self::names($data['order'] ?? []),
            hidden: self::names($data['hidden'] ?? []),
            default: self::name($data['default'] ?? null),
            lastUsed: self::name($data['last_used'] ?? null),
            autoPin: (($autoPin === self::OFF) || (is_string($autoPin) && AutoPin::tryFrom($autoPin))) ? $autoPin : null,
            usage: self::usage($data['usage'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order' => $this->order,
            'hidden' => $this->hidden,
            'default' => $this->default,
            'last_used' => $this->lastUsed,
            'auto_pin' => $this->autoPin,
            'usage' => $this->usage,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->order === []
            && $this->hidden === []
            && $this->default === null
            && $this->lastUsed === null
            && $this->autoPin === null
            && $this->usage === [];
    }

    /**
     * Count one pick of an entry. Every pick is worth 1 and loses half of
     * that every half-life, so "most used lately" needs no history: the
     * faded sum and the time of the last pick are enough.
     */
    public function recordUse(string $name, int $now, float $halfLife): void
    {
        if (self::name($name) === null) {
            return;
        }

        $entry = $this->usage[$name] ?? ['count' => 0, 'score' => 0.0, 'at' => $now];

        $this->usage[$name] = [
            'count' => $entry['count'] + 1,
            'score' => round(self::faded($entry, $now, $halfLife) + 1, 4),
            'at' => $now,
        ];

        $this->lastUsed = $name;

        if (count($this->usage) > self::MAX_ENTRIES) {
            uasort($this->usage, fn (array $a, array $b): int => self::faded($b, $now, $halfLife) <=> self::faded($a, $now, $halfLife));

            $this->usage = array_slice($this->usage, 0, self::MAX_ENTRIES, preserve_keys: true);
        }
    }

    /**
     * The entry a strategy puts on the button, out of the ones that can
     * sit there — or null while the user has not picked any of them.
     *
     * @param  array<string>  $candidates
     */
    public function pick(AutoPin $strategy, array $candidates, int $now, float $halfLife): ?string
    {
        if ($strategy === AutoPin::Recent) {
            return in_array($this->lastUsed, $candidates, true) ? $this->lastUsed : null;
        }

        $best = null;
        $bestRank = null;

        foreach ($candidates as $name) {
            if (($entry = $this->usage[$name] ?? null) === null) {
                continue;
            }

            // On a tie the entry picked later wins, then the one listed first.
            $rank = [
                $strategy === AutoPin::Frequent ? $entry['count'] : self::faded($entry, $now, $halfLife),
                $entry['at'],
            ];

            if (($bestRank === null) || (($rank <=> $bestRank) > 0)) {
                $best = $name;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * The same layout after actions were renamed (old name => new name),
     * so users keep the place, the pin and the picks of a renamed entry.
     * Returns this very layout when it holds none of the old names.
     *
     * @param  array<string, string>  $renames
     */
    public function renamed(array $renames): self
    {
        $renames = array_filter(
            $renames,
            fn (mixed $new, int | string $old): bool => (self::name($new) !== null) && (((string) $old) !== $new),
            ARRAY_FILTER_USE_BOTH,
        );

        $holds = fn (int | string $old): bool => in_array($old, $this->order, true)
            || in_array($old, $this->hidden, true)
            || ($this->default === $old)
            || ($this->lastUsed === $old)
            || isset($this->usage[$old]);

        if (array_filter(array_keys($renames), $holds) === []) {
            return $this;
        }

        $rename = fn (?string $name): ?string => $name === null ? null : ($renames[$name] ?? $name);
        $usage = [];

        foreach ($this->usage as $name => $entry) {
            $name = (string) $rename((string) $name);

            // What the user already did with the new name stays as it is.
            $usage[$name] ??= $this->usage[$name] ?? $entry;
        }

        return new self(
            order: array_values(array_unique(array_map($rename, $this->order))),
            hidden: array_values(array_unique(array_map($rename, $this->hidden))),
            default: $rename($this->default),
            lastUsed: $rename($this->lastUsed),
            autoPin: $this->autoPin,
            usage: $usage,
        );
    }

    /**
     * @param  array{count: int, score: float, at: int}  $entry
     */
    protected static function faded(array $entry, int $now, float $halfLife): float
    {
        return $entry['score'] * (0.5 ** (max(0, $now - $entry['at']) / max(1.0, $halfLife)));
    }

    /**
     * @return array<string, array{count: int, score: float, at: int}>
     */
    protected static function usage(mixed $usage): array
    {
        if (! is_array($usage)) {
            return [];
        }

        $clean = [];

        foreach ($usage as $name => $entry) {
            $name = (string) $name;

            if ((self::name($name) === null) || (! is_array($entry)) || (count($clean) >= self::MAX_ENTRIES)) {
                continue;
            }

            $count = (int) ($entry['count'] ?? 0);

            if ($count < 1) {
                continue;
            }

            $clean[$name] = [
                'count' => $count,
                'score' => max(0.0, (float) ($entry['score'] ?? $count)),
                'at' => max(0, (int) ($entry['at'] ?? 0)),
            ];
        }

        return $clean;
    }

    /**
     * @return array<string>
     */
    protected static function names(mixed $names): array
    {
        if (! is_array($names)) {
            return [];
        }

        $names = array_filter(array_map(self::name(...), $names), fn (?string $name): bool => $name !== null);

        return array_slice(array_values(array_unique($names)), 0, self::MAX_ENTRIES);
    }

    protected static function name(mixed $name): ?string
    {
        if (! is_string($name) || $name === '' || strlen($name) > 255) {
            return null;
        }

        return $name;
    }
}
