<?php

namespace Wyvern\Content;

use App\Models\EggVariable;
use App\Models\Server;
use Illuminate\Database\Query\JoinClause;
use Wyvern\Minecraft\Loader;

/**
 * What a server runs, as far as content is concerned.
 *
 * Servers on the Wyvern egg state it outright in MC_LOADER. Servers on one of the older
 * single-flavour eggs do not, so the egg's own name is the next best evidence — and if
 * neither says anything we say so, rather than guessing wrong and offering Fabric mods
 * to a Paper server.
 */
final readonly class ServerProfile
{
    public function __construct(
        public ?Loader $loader,
        public ?string $gameVersion,
    ) {}

    public static function of(Server $server): self
    {
        $env = self::environment($server);

        return new self(
            self::loaderOf($server, $env),
            self::first($env, ['MC_VERSION', 'MINECRAFT_VERSION', 'VERSION']),
        );
    }

    public function isKnown(): bool
    {
        return $this->loader !== null;
    }

    /** @return ContentType[] */
    public function installableTypes(): array
    {
        return $this->loader ? ContentType::forLoader($this->loader) : [];
    }

    /**
     * The server's variables, read straight from the tables.
     *
     * Server::variables() builds server_value from a join filtered on $this->id, which
     * is not bound during eager loading — so that relation silently returns nulls when
     * the caller happened to use with('variables'). Querying here instead makes this
     * independent of how the server was loaded.
     *
     * @return array<string, string>
     */
    private static function environment(Server $server): array
    {
        return EggVariable::query()
            ->where('egg_variables.egg_id', $server->egg_id)
            ->leftJoin('server_variables', function (JoinClause $join) use ($server) {
                $join->on('server_variables.variable_id', 'egg_variables.id')
                    ->where('server_variables.server_id', $server->id);
            })
            ->selectRaw('egg_variables.env_variable, COALESCE(server_variables.variable_value, egg_variables.default_value) as value')
            ->pluck('value', 'env_variable')
            ->all();
    }

    /** @param array<string, string> $env */
    private static function loaderOf(Server $server, array $env): ?Loader
    {
        $declared = $env['MC_LOADER'] ?? null;

        if (filled($declared) && ($loader = Loader::tryFrom(mb_strtolower($declared)))) {
            return $loader;
        }

        $name = mb_strtolower($server->egg?->name ?? '');

        // Ordered so neoforge wins before forge, which is a substring of it.
        foreach ([Loader::NeoForge, Loader::Purpur, Loader::Paper, Loader::Fabric, Loader::Forge, Loader::Vanilla] as $loader) {
            if (str_contains($name, $loader->value)) {
                return $loader;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $env
     * @param  string[]  $names
     */
    private static function first(array $env, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $env[$name] ?? null;

            // "latest" is a instruction to the installer, not a version anyone can
            // filter a mod list by.
            if (filled($value) && $value !== 'latest') {
                return $value;
            }
        }

        return null;
    }
}
