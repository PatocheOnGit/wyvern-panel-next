<?php

namespace Wyvern\Servers;

use App\Enums\CustomizationKey;
use App\Models\Server;
use App\Models\User;

/**
 * Pinned servers, stored per user.
 *
 * Kept in the existing `customization` JSON rather than a new table: it is a display
 * preference, it belongs to exactly one user, and nothing else ever needs to join on it.
 *
 * Pins hold the server UUID, not its id. A UUID survives the server being moved between
 * installs, and — more usefully here — a stale pin to a deleted server is simply a UUID
 * that matches nothing, so it drops out of every query without needing to be cleaned up.
 */
final class Pins
{
    /** @return list<string> */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $pinned = $user->getCustomization(CustomizationKey::PinnedServers);

        return is_array($pinned) ? array_values(array_filter($pinned, 'is_string')) : [];
    }

    public static function has(?User $user, Server $server): bool
    {
        return in_array($server->uuid, self::for($user), true);
    }

    /** @return bool the state the server is now in */
    public static function toggle(User $user, Server $server): bool
    {
        $pinned = self::for($user);
        $isPinned = in_array($server->uuid, $pinned, true);

        $pinned = $isPinned
            ? array_values(array_diff($pinned, [$server->uuid]))
            : [...$pinned, $server->uuid];

        // Read, modify, write the whole map — the column is a single JSON document and
        // getCustomization() merges the defaults in, so writing back what it returned
        // keeps every other preference intact.
        $customization = $user->getCustomization();
        $customization[CustomizationKey::PinnedServers->value] = $pinned;

        $user->update(['customization' => $customization]);

        return !$isPinned;
    }
}
