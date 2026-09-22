<?php

namespace Wyvern\Minecraft\Players;

use App\Models\Server;

/**
 * The Server List Ping: what the multiplayer screen asks a server.
 *
 * Gives the online count, the limit and a sample of up to twelve names. A server with
 * hide-online-players answers with no sample at all.
 */
class StatusPing
{
    /** @return array{online: int, max: int, players: list<array{name: string, id: string}>, version: ?string}|null */
    public function ping(Server $server, float $timeout = 1.5): ?array
    {
        $allocation = $server->allocation;

        if ($allocation === null) {
            return null;
        }

        // A wildcard bind is reachable through the node's own address.
        $host = in_array($allocation->ip, ['0.0.0.0', '::'], true) ? $server->node->fqdn : $allocation->ip;

        $socket = @fsockopen($host, $allocation->port, $errno, $error, $timeout);

        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, (int) ceil($timeout));

        try {
            $handshake = "\x00" . self::varint(767) . self::string($host) . pack('n', $allocation->port) . self::varint(1);
            fwrite($socket, self::varint(strlen($handshake)) . $handshake . "\x01\x00");

            self::readVarint($socket);

            if (self::readVarint($socket) !== 0) {
                return null;
            }

            $json = self::read($socket, self::readVarint($socket));
        } catch (\RuntimeException) {
            return null;
        } finally {
            fclose($socket);
        }

        $status = json_decode($json, true);

        if (!is_array($status)) {
            return null;
        }

        return [
            'online' => (int) ($status['players']['online'] ?? 0),
            'max' => (int) ($status['players']['max'] ?? 0),
            'players' => array_values(array_filter(
                $status['players']['sample'] ?? [],
                // Some plugins pad the sample with fake lines of text.
                fn ($p) => is_array($p) && isset($p['name'], $p['id']) && $p['id'] !== '00000000-0000-0000-0000-000000000000',
            )),
            'version' => $status['version']['name'] ?? null,
        ];
    }

    private static function varint(int $value): string
    {
        $out = '';
        $value &= 0xFFFFFFFF;

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value !== 0 ? $byte | 0x80 : $byte);
        } while ($value !== 0);

        return $out;
    }

    private static function string(string $value): string
    {
        return self::varint(strlen($value)) . $value;
    }

    /** @param resource $socket */
    private static function readVarint($socket): int
    {
        $value = 0;

        for ($i = 0; $i < 5; $i++) {
            $byte = ord(self::read($socket, 1));
            $value |= ($byte & 0x7F) << (7 * $i);

            if (($byte & 0x80) === 0) {
                return $value;
            }
        }

        throw new \RuntimeException('VarInt too long');
    }

    /** @param resource $socket */
    private static function read($socket, int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Connection closed');
            }

            $data .= $chunk;
        }

        return $data;
    }
}
