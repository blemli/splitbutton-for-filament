<?php

namespace Blemli\SplitButton\Enums;

/**
 * What a split button puts on its face by itself, from how the user picks.
 */
enum AutoPin: string
{
    /** The entry picked last. */
    case Recent = 'recent';

    /** The entry picked most often, ever. */
    case Frequent = 'frequent';

    /** The entry picked most often lately: every pick counts, old picks fade. */
    case Frecent = 'frecent';

    /**
     * Whatever a developer may pass — a case, its string, a list of
     * either, true (frecent) or false (nothing) — as a list of cases.
     *
     * @return array<self>
     */
    public static function list(mixed $value): array
    {
        if ($value === true) {
            return [self::Frecent];
        }

        $strategies = [];

        foreach (is_array($value) ? $value : [$value] as $strategy) {
            $strategy = $strategy instanceof self
                ? $strategy
                : (is_string($strategy) ? self::tryFrom($strategy) : null);

            if (($strategy !== null) && (! in_array($strategy, $strategies, true))) {
                $strategies[] = $strategy;
            }
        }

        return $strategies;
    }

    /**
     * Which of the offered strategies is on before the user chose: true is
     * the first one offered, false none.
     *
     * @param  array<self>  $offered
     */
    public static function initial(self | string | bool | null $byDefault, array $offered): ?self
    {
        if (($byDefault === null) || ($byDefault === false) || ($offered === [])) {
            return null;
        }

        if ($byDefault === true) {
            return $offered[0];
        }

        $strategy = $byDefault instanceof self ? $byDefault : self::tryFrom($byDefault);

        return in_array($strategy, $offered, true) ? $strategy : null;
    }
}
