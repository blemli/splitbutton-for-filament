<?php

namespace Blemli\SplitButton;

/**
 * One user's arrangement of one split button: the order of its entries,
 * the ones they hid, the one they put on the button face and the one they
 * picked last.
 */
final class Layout
{
    public const MAX_ENTRIES = 100;

    /**
     * @param  array<string>  $order
     * @param  array<string>  $hidden
     */
    public function __construct(
        public array $order = [],
        public array $hidden = [],
        public ?string $default = null,
        public ?string $lastUsed = null,
        public ?bool $rememberLast = null,
    ) {}

    /**
     * @param  array<string, mixed> | null  $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            order: self::names($data['order'] ?? []),
            hidden: self::names($data['hidden'] ?? []),
            default: self::name($data['default'] ?? null),
            lastUsed: self::name($data['last_used'] ?? null),
            rememberLast: isset($data['remember_last']) ? (bool) $data['remember_last'] : null,
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
            'remember_last' => $this->rememberLast,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->order === []
            && $this->hidden === []
            && $this->default === null
            && $this->lastUsed === null
            && $this->rememberLast === null;
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
