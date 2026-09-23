<?php

namespace Wyvern\FiveM;

use App\Models\EggVariable;
use App\Models\Server;
use Illuminate\Database\Query\JoinClause;

/** What the panel knows about a FiveM or RedM server, from its egg variables. */
final readonly class FiveMServer
{
    public const CFG = '/server.cfg';

    /** @param array<string, string> $env */
    private function __construct(public Server $server, public array $env) {}

    public static function of(Server $server): ?self
    {
        $env = EggVariable::query()
            ->where('egg_variables.egg_id', $server->egg_id)
            ->leftJoin('server_variables', function (JoinClause $join) use ($server) {
                $join->on('server_variables.variable_id', 'egg_variables.id')
                    ->where('server_variables.server_id', $server->id);
            })
            ->selectRaw('egg_variables.env_variable, COALESCE(server_variables.variable_value, egg_variables.default_value) as value')
            ->pluck('value', 'env_variable')
            ->map(fn ($v) => (string) $v)
            ->all();

        return isset($env['FIVEM_VERSION']) || isset($env['TXHOST_GAME_NAME']) ? new self($server, $env) : null;
    }

    public static function is(Server $server): bool
    {
        return self::of($server) !== null;
    }

    public function game(): string
    {
        return ($this->env['TXHOST_GAME_NAME'] ?? 'fivem') === 'redm' ? 'redm' : 'fivem';
    }

    public function usesTxAdmin(): bool
    {
        return in_array(strtolower($this->env['TXADMIN_ENABLE'] ?? '0'), ['1', 'true'], true);
    }

    public function txAdminPort(): ?int
    {
        $port = (int) ($this->env['TXHOST_TXA_PORT'] ?? 0);

        return $port > 0 ? $port : null;
    }

    /** Whether the txAdmin port is one of this server's allocations, so it is reachable. */
    public function txAdminReachable(): bool
    {
        return $this->server->allocations()->where('port', $this->txAdminPort())->exists();
    }

    /** The address players and browsers use: the alias, else the node's own name. */
    public function host(): string
    {
        $allocation = $this->server->allocation;

        if ($allocation?->ip_alias) {
            return $allocation->ip_alias;
        }

        return in_array($allocation?->ip, ['0.0.0.0', '::', null], true) ? $this->server->node->fqdn : $allocation->ip;
    }

    public function port(): int
    {
        return (int) $this->server->allocation?->port;
    }
}
