<?php

namespace Blemli\SplitButton\Support;

use BackedEnum;
use Blemli\SplitButton\SplitButton;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * A menu entry for a record, by convention — what a developer would
 * otherwise write out for every record they return from ->options():
 *
 *  - its name is the model and the key ("preset-7"), so users keep its place
 *  - its label is the record's title, its icon the one of its resource
 *  - behind a CreateAction it leads to the page's create form with the
 *    record in the query string (?preset=7): "a new one from this"
 *  - anywhere else it leads to the record itself (edit, or view)
 */
class RecordOption
{
    /**
     * @param  array{url?: Closure | null, label?: Closure | null, icon?: Closure | null, name?: Closure | null}  $using
     */
    public static function make(SplitButton $button, Model $record, array $using = []): Action
    {
        $custom = fn (string $what): mixed => isset($using[$what])
            ? $button->evaluate($using[$what], ['record' => $record], [Model::class => $record, $record::class => $record])
            : null;

        $name = (string) ($custom('name') ?? static::name($record));

        $url = $custom('url') ?? static::url($button, $record);

        if (blank($url)) {
            throw new LogicException(
                "Split button option [{$name}]: no convention says where it leads. "
                . 'Its model has no Filament resource and the button does not start with a CreateAction on a resource page. '
                . 'Pass url: fn ($record) => … to ->options().',
            );
        }

        $action = Action::make($name)
            ->label($custom('label') ?? static::label($record))
            ->url($url);

        if (($icon = $custom('icon') ?? static::icon($record)) !== null) {
            $action->icon($icon);
        }

        return $action;
    }

    /**
     * "preset-7": the same for the same record on every request.
     */
    public static function name(Model $record): string
    {
        return Str::kebab(class_basename($record)) . '-' . $record->getKey();
    }

    protected static function label(Model $record): string | Htmlable
    {
        $resource = static::resourceOf($record);

        if (($resource !== null) && filled($resource::getRecordTitleAttribute())) {
            $title = $resource::getRecordTitle($record);

            if (filled($title)) {
                return $title;
            }
        }

        foreach (['name', 'title', 'label'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_string($value) && ($value !== '')) {
                return $value;
            }
        }

        return class_basename($record) . ' #' . $record->getKey();
    }

    protected static function icon(Model $record): string | BackedEnum | Htmlable | null
    {
        return static::resourceOf($record)?->getNavigationIcon();
    }

    protected static function url(SplitButton $button, Model $record): ?string
    {
        try {
            $host = static::hostResourceOf($button);

            if (($host !== null) && ($button->getDeclaredAction() instanceof CreateAction) && $host::hasPage('create')) {
                return $host::getUrl('create', [Str::snake(class_basename($record)) => $record->getKey()]);
            }

            return static::resourceOf($record)?->getGlobalSearchResultUrl($record);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The resource of the record's model on the current panel. Returned as
     * an instance only so its static methods can be called null-safely.
     */
    protected static function resourceOf(Model $record): ?object
    {
        try {
            $resource = Filament::getModelResource($record);

            return filled($resource) && class_exists($resource) ? app($resource) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The resource of the page the button is on.
     *
     * @return class-string | null
     */
    protected static function hostResourceOf(SplitButton $button): ?string
    {
        try {
            $livewire = $button->getLivewire();

            return method_exists($livewire, 'getResource') ? $livewire::getResource() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
