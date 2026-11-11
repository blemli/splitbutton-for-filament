<?php

namespace Blemli\SplitButton\Support;

use ParseError;

/**
 * Writes a plugin registration into the source of a Filament panel
 * provider. Works on the tokens of the file, never on patterns in its
 * text, and hands back nothing rather than code it is not sure of: the
 * provider is the developer's file.
 */
class PanelRegistrar
{
    /**
     * The provider's source with `$call` added to its ->plugins([...]) list
     * (or a new list right after `return $panel`) and `$class` imported —
     * or null when the file has no place this can safely go.
     *
     * @param  class-string  $class
     * @param  string  $call  e.g. "SplitButtonPlugin::make()->persist()"
     */
    public static function register(string $code, string $class, string $call): ?string
    {
        if (($tokens = static::tokens($code)) === null) {
            return null;
        }

        $offsets = [];
        $offset = 0;

        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }

        $edits = static::intoPluginsList($code, $tokens, $offsets, $call)
            ?? static::afterReturnPanel($code, $tokens, $offsets, $call);

        if ($edits === null) {
            return null;
        }

        $import = static::import($tokens, $offsets, $class);

        if ($import === null) {
            // Nowhere to import it: write the class out in full instead.
            $short = static::shortName($class);

            foreach ($edits as $position => $text) {
                $edits[$position] = str_replace("{$short}::", "\\{$class}::", $text);
            }
        } else {
            $edits += $import;
        }

        // Back to front, so earlier positions stay where they are — and
        // with the line endings the file already has.
        krsort($edits);
        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";

        foreach ($edits as $position => $text) {
            $code = substr($code, 0, $position) . str_replace("\n", $eol, $text) . substr($code, $position);
        }

        // Never hand back a file that no longer parses.
        return static::tokens($code) === null ? null : $code;
    }

    /**
     * @return array<int, array{int, string, int} | string> | null
     */
    protected static function tokens(string $code): ?array
    {
        try {
            return token_get_all($code, TOKEN_PARSE);
        } catch (ParseError) {
            return null;
        }
    }

    /**
     * The id a panel provider gives its panel: ->id('admin').
     */
    public static function panelId(string $code): ?string
    {
        return preg_match('/->id\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $code, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * At the end of an existing ->plugins([...]) list.
     *
     * @param  array<int, array{int, string, int} | string>  $tokens
     * @param  array<int, int>  $offsets
     * @return array<int, string> | null text to insert, by position
     */
    protected static function intoPluginsList(string $code, array $tokens, array $offsets, string $call): ?array
    {
        foreach ($tokens as $index => $token) {
            if ((! is_array($token)) || ($token[0] !== T_STRING) || ($token[1] !== 'plugins')) {
                continue;
            }

            $before = static::previous($tokens, $index);
            $paren = static::next($tokens, $index);
            $open = $paren === null ? null : static::next($tokens, $paren);

            if (($before === null) || (! static::is($tokens[$before], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) || ($tokens[$paren] !== '(') || ($open === null) || ($tokens[$open] !== '[')) {
                continue;
            }

            if (($close = static::closing($tokens, $open)) === null) {
                return null;
            }

            $last = static::previous($tokens, $close);
            $indent = static::indentOf($code, $offsets[$index]);
            $isEmpty = $last === $open;
            $isMultiline = str_contains(substr($code, $offsets[$open], $offsets[$close] - $offsets[$open]), "\n");

            if (! $isMultiline) {
                return [$offsets[$close] => ($isEmpty ? '' : ', ') . $call];
            }

            $edits = [];
            $afterLast = $offsets[$last] + strlen(is_array($tokens[$last]) ? $tokens[$last][1] : $tokens[$last]);

            if ((! $isEmpty) && ($tokens[$last] !== ',')) {
                $edits[$afterLast] = ',';
            }

            // Before the line break that leads to the closing bracket, so a
            // comment behind the last entry stays behind the last entry.
            $whitespace = $close - 1;
            $position = static::is($tokens[$whitespace], [T_WHITESPACE]) ? $offsets[$whitespace] : $offsets[$close];

            $edits[$position] = ($edits[$position] ?? '') . "\n{$indent}    {$call},";

            return $edits;
        }

        return null;
    }

    /**
     * A new ->plugins([...]) call at the end of the `return $panel->…;`
     * chain, for a provider as `filament:install --panels` writes it.
     *
     * @param  array<int, array{int, string, int} | string>  $tokens
     * @param  array<int, int>  $offsets
     * @return array<int, string> | null
     */
    protected static function afterReturnPanel(string $code, array $tokens, array $offsets, string $call): ?array
    {
        foreach ($tokens as $index => $token) {
            if (! static::is($token, [T_RETURN])) {
                continue;
            }

            $variable = static::next($tokens, $index);
            $chain = $variable === null ? null : static::next($tokens, $variable);

            if (($variable === null) || (! static::is($tokens[$variable], [T_VARIABLE])) || ($tokens[$variable][1] !== '$panel') || ($chain === null) || (! static::is($tokens[$chain], [T_OBJECT_OPERATOR]))) {
                continue;
            }

            // The semicolon that ends the chain, not one inside a closure.
            $depth = 0;

            for ($end = $chain; isset($tokens[$end]); $end++) {
                if (in_array($tokens[$end], ['(', '[', '{'], true) || static::is($tokens[$end], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE])) {
                    $depth++;
                } elseif (in_array($tokens[$end], [')', ']', '}'], true)) {
                    $depth--;
                } elseif (($tokens[$end] === ';') && ($depth === 0)) {
                    $indent = static::indentOf($code, $offsets[$index]) . '    ';

                    return [$offsets[$end] => "\n{$indent}->plugins([\n{$indent}    {$call},\n{$indent}])"];
                }
            }

            return null;
        }

        return null;
    }

    /**
     * `use <class>;` behind the file's last import — nothing when it is
     * imported already, null when the file has no namespace to import into.
     *
     * @param  array<int, array{int, string, int} | string>  $tokens
     * @param  array<int, int>  $offsets
     * @param  class-string  $class
     * @return array<int, string> | null
     */
    protected static function import(array $tokens, array $offsets, string $class): ?array
    {
        $anchor = null;
        $isFirstImport = true;

        foreach ($tokens as $index => $token) {
            if (static::is($token, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                break;
            }

            if (! static::is($token, [T_NAMESPACE, T_USE])) {
                continue;
            }

            for ($end = $index; isset($tokens[$end]) && ($tokens[$end] !== ';'); $end++) {
                if (static::is($tokens[$end], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && (ltrim($tokens[$end][1], '\\') === $class) && static::is($token, [T_USE])) {
                    return [];
                }
            }

            if (! isset($tokens[$end])) {
                return null;
            }

            $anchor = $offsets[$end] + 1;
            $isFirstImport = static::is($token, [T_NAMESPACE]);
        }

        if ($anchor === null) {
            return null;
        }

        return [$anchor => ($isFirstImport ? "\n\n" : "\n") . "use {$class};"];
    }

    /**
     * @param  array<int, array{int, string, int} | string>  $tokens
     */
    protected static function closing(array $tokens, int $open): ?int
    {
        $depth = 0;

        for ($index = $open; isset($tokens[$index]); $index++) {
            if (($tokens[$index] === '[') || static::is($tokens[$index], [T_ATTRIBUTE])) {
                $depth++;
            } elseif ($tokens[$index] === ']') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{int, string, int} | string>  $tokens
     */
    protected static function next(array $tokens, int $index): ?int
    {
        for ($index++; isset($tokens[$index]); $index++) {
            if (! static::is($tokens[$index], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{int, string, int} | string>  $tokens
     */
    protected static function previous(array $tokens, int $index): ?int
    {
        for ($index--; isset($tokens[$index]); $index--) {
            if (! static::is($tokens[$index], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array{int, string, int} | string  $token
     * @param  array<int>  $kinds
     */
    protected static function is(array | string $token, array $kinds): bool
    {
        return is_array($token) && in_array($token[0], $kinds, true);
    }

    /**
     * The whitespace the line at this position starts with.
     */
    protected static function indentOf(string $code, int $position): string
    {
        $lineStart = strrpos(substr($code, 0, $position), "\n");
        $line = substr($code, $lineStart === false ? 0 : $lineStart + 1);

        return substr($line, 0, strspn($line, " \t"));
    }

    protected static function shortName(string $class): string
    {
        return substr($class, (int) strrpos('\\' . $class, '\\'));
    }
}
