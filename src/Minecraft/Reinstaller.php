<?php

namespace Wyvern\Minecraft;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\ReinstallServerService;
use App\Services\Servers\VariableValidatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Switches a server's flavour or version: variables, Java image, then a reinstall. */
class Reinstaller
{
    public const VARIABLES = ['MC_LOADER', 'MC_VERSION', 'MC_BUILD'];

    public function __construct(
        private readonly VariableValidatorService $validator,
        private readonly ReinstallServerService $reinstall,
    ) {}

    /**
     * The values a server owner may set, checked against the egg's own rules.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function validate(Server $server, array $values): array
    {
        $values = array_map('strval', array_intersect_key($values, array_flip(self::VARIABLES)));

        return $this->validator->handle($server->egg_id, $values)
            ->filter(fn (object $variable) => array_key_exists($variable->key, $values))
            ->mapWithKeys(fn (object $variable) => [$variable->key => (string) $variable->value])
            ->all();
    }

    /**
     * One transaction: a reinstall the daemon refuses leaves nothing changed.
     *
     * @param  array<string, string>  $values  already validated
     */
    public function apply(Server $server, array $values, ?string $image = null): void
    {
        try {
            DB::transaction(function () use ($server, $values, $image) {
                $variables = EggVariable::query()
                    ->where('egg_id', $server->egg_id)
                    ->whereIn('env_variable', array_keys($values))
                    ->pluck('id', 'env_variable');

                foreach ($values as $env => $value) {
                    if (isset($variables[$env])) {
                        ServerVariable::query()->updateOrCreate(
                            ['server_id' => $server->id, 'variable_id' => $variables[$env]],
                            ['variable_value' => $value],
                        );
                    }
                }

                if ($image !== null && $image !== $server->image) {
                    $server->forceFill(['image' => $image])->saveOrFail();
                }

                $this->reinstall->handle($server);
            });
        } catch (\Throwable $e) {
            $server->refresh();

            throw $e;
        }
    }

    /** The egg image a Minecraft version needs, or null when the current one is right. */
    public function imageFor(Server $server, Loader $loader, string $version, VersionCatalogue $catalogue): ?string
    {
        $resolved = $catalogue->resolve($loader, $version);
        $required = $resolved ? $catalogue->javaVersion($resolved) : null;
        $image = $required ? JavaImage::for($server->egg, $required) : null;

        return $image !== $server->image ? $image : null;
    }
}
