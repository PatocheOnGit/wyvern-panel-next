<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Wyvern\Content\ServerProfile;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\Files\ServerProperties;
use Wyvern\Minecraft\Properties\Motd;
use Wyvern\Minecraft\Properties\PropertyCatalog;

/** server.properties as a form: every key the file has, grouped and explained. */
class Properties extends Page
{
    use BlockAccessInConflict;
    use InteractsWithForms;

    public const PERMISSION = 'minecraft.properties';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Adjustments;

    protected static ?int $navigationSort = 2;

    protected string $view = 'wyvern.server.properties';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var list<string> keys present in the file, in display order */
    public array $keys = [];

    /** @var array<string, string> values as last read or saved */
    public array $original = [];

    public bool $exists = false;

    protected MinecraftFiles $files;

    public function boot(MinecraftFiles $files): void
    {
        $this->files = $files;
    }

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $properties = $this->files->properties($this->server());

        $this->exists = $properties !== null;
        $this->original = $properties?->all() ?? [];
        $this->keys = PropertyCatalog::sort(array_keys($this->original));

        $state = [];

        foreach ($this->original as $key => $value) {
            $state[PropertyCatalog::slug($key)] = $this->toState($key, $value);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (PropertyCatalog::GROUPS as $group) {
            $fields = [];

            foreach ($this->keys as $key) {
                if (!PropertyCatalog::isManaged($key) && PropertyCatalog::definition($key)['group'] === $group) {
                    $fields[] = $this->field($key);
                }
            }

            if ($fields !== []) {
                $sections[] = Section::make(trans("wyvern.properties.groups.$group"))
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema($fields);
            }
        }

        return $schema
            ->components($sections)
            ->statePath('data')
            ->disabled(fn () => !$this->canEdit());
    }

    private function field(string $key): Field
    {
        $definition = PropertyCatalog::definition($key);
        $slug = PropertyCatalog::slug($key);

        $field = match ($definition['type']) {
            'bool' => Toggle::make($slug),
            'int' => TextInput::make($slug)
                ->integer()
                ->minValue($definition['min'] ?? null)
                ->maxValue($definition['max'] ?? null),
            'enum' => Select::make($slug)
                ->options($this->options($key, $definition['options'] ?? []))
                ->selectablePlaceholder(false)
                ->native(false),
            'motd' => Textarea::make($slug)
                ->rows(2)
                ->live(debounce: 400)
                ->columnSpanFull()
                ->belowContent(fn (Get $get) => [
                    Text::make(new HtmlString('<span class="wy-motd">' . Motd::html((string) $get($slug)) . '</span>')),
                    trans('wyvern.properties.motd_help'),
                ]),
            'password' => TextInput::make($slug)->password()->revealable(),
            default => TextInput::make($slug),
        };

        $label = "wyvern.properties.keys.$slug.label";
        $help = "wyvern.properties.keys.$slug.help";

        $field = $field
            ->label(Lang::has($label) ? trans($label) : $key)
            ->hint($key);

        // The MOTD's help already sits under its preview.
        if (Lang::has($help) && $definition['type'] !== 'motd') {
            $field->helperText(trans($help));
        }

        return $field;
    }

    /**
     * @param  list<string>  $options
     * @return array<string, string>
     */
    private function options(string $key, array $options): array
    {
        $current = $this->original[$key] ?? null;

        // Older versions spell some values differently; keep whatever the file has.
        if ($current !== null && !in_array($current, $options, true)) {
            $options[] = $current;
        }

        $labels = [];

        foreach ($options as $option) {
            $label = 'wyvern.properties.options.' . PropertyCatalog::slug($key) . '.' . str_replace([':', '.', '-'], '_', $option);
            $labels[$option] = Lang::has($label) ? trans($label) : $option;
        }

        return $labels;
    }

    private function toState(string $key, string $value): mixed
    {
        return match (PropertyCatalog::definition($key)['type']) {
            'bool' => strtolower($value) === 'true',
            'int' => is_numeric($value) ? (int) $value : $value,
            default => $value,
        };
    }

    private function fromState(string $key, mixed $value): string
    {
        return match (PropertyCatalog::definition($key)['type']) {
            'bool' => $value ? 'true' : 'false',
            default => (string) ($value ?? ''),
        };
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $state = $this->form->getState();
        $server = $this->server();
        $properties = $this->files->properties($server) ?? ServerProperties::parse('');
        $changed = [];

        foreach ($this->keys as $key) {
            if (PropertyCatalog::isManaged($key)) {
                continue;
            }

            $value = $this->fromState($key, $state[PropertyCatalog::slug($key)] ?? null);

            // Compared with what was loaded, so an edit made elsewhere meanwhile survives.
            if ($value !== ($this->original[$key] ?? null)) {
                $properties->set($key, $value);
                $changed[] = $key;
            }
        }

        if ($changed === []) {
            Notification::make()->title(trans('wyvern.properties.notifications.unchanged'))->send();

            return;
        }

        $this->files->saveProperties($server, $properties);
        $this->original = $properties->all();

        // Keys only: values can be secrets.
        Activity::event('server:wyvern.properties')
            ->property('keys', implode(', ', $changed))
            ->log();

        $running = $server->retrieveStatus()->isStoppable();

        Notification::make()
            ->title(trans('wyvern.properties.notifications.saved'))
            ->body($running ? trans('wyvern.properties.notifications.restart') : null)
            ->actions($running && user()?->can(SubuserPermission::ControlRestart, $server) ? [
                Action::make('restart')
                    ->label(trans('server/console.power_actions.restart'))
                    ->button()
                    ->close()
                    ->dispatch('wyvern-restart-server'),
            ] : [])
            ->success()
            ->send();
    }

    #[On('wyvern-restart-server')]
    public function restartServer(): void
    {
        if (user()?->can(SubuserPermission::ControlRestart, $this->server())) {
            PowerActions::send('restart');
        }
    }

    /** @return array<string, string> */
    public function managed(): array
    {
        return array_intersect_key($this->original, array_flip(PropertyCatalog::MANAGED));
    }

    public function canEdit(): bool
    {
        return user()?->can(SubuserPermission::FileUpdate, $this->server()) ?? false;
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            $this->saveAction(),
            PowerActions::group(),
        ];
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(trans('wyvern.properties.save'))
            ->icon(TablerIcon::DeviceFloppy)
            ->button()
            ->keyBindings(['mod+s'])
            ->visible(fn () => $this->exists && $this->canEdit())
            ->action(fn () => $this->save());
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && ServerProfile::of($server)->isKnown()
            && (user()?->can(self::PERMISSION, $server) ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.game');
    }

    public static function getNavigationLabel(): string
    {
        return trans('wyvern.properties.title');
    }

    public function getTitle(): string
    {
        return trans('wyvern.properties.title');
    }
}
