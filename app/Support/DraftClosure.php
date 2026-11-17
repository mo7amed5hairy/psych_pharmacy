<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Remembers draft UUIDs that were successfully written into the real tables
 * and then deleted from the drafts table.
 *
 * Why a guard is needed: the browser syncs a draft every second. A delayed
 * request (slow network, another tab...) could arrive after the real save and
 * re-create the row. The guard makes those requests return "closed" instead
 * of inserting a draft again.
 *
 * The guard lives in the cache (not in a database table) because the drafts
 * table must only contain unsaved data. Entries expire automatically.
 */
class DraftClosure
{
    /** How long a closed UUID is remembered (the client also prunes locally). */
    public const TTL_DAYS = 14;

    /**
     * Mark a draft UUID as already saved for its owner.
     */
    public static function close(int $userId, string $uuid): void
    {
        if ($userId <= 0 || $uuid === '') {
            return;
        }

        $cutoff = now()->subDays(self::TTL_DAYS)->getTimestamp();
        $map = array_filter(
            self::all($userId),
            static fn ($closedAt) => is_numeric($closedAt) && (int) $closedAt >= $cutoff
        );

        $map[$uuid] = now()->getTimestamp();

        Cache::put(self::key($userId), $map, now()->addDays(self::TTL_DAYS));
    }

    /**
     * Bulk version used when purging legacy tombstones: one cache write per
     * user instead of one per draft.
     *
     * @param  array<int, string>  $uuids
     */
    public static function closeMany(int $userId, array $uuids): void
    {
        if ($userId <= 0 || $uuids === []) {
            return;
        }

        $cutoff = now()->subDays(self::TTL_DAYS)->getTimestamp();
        $map = array_filter(
            self::all($userId),
            static fn ($closedAt) => is_numeric($closedAt) && (int) $closedAt >= $cutoff
        );

        $closedAt = now()->getTimestamp();

        foreach ($uuids as $uuid) {
            if (is_string($uuid) && $uuid !== '') {
                $map[$uuid] = $closedAt;
            }
        }

        Cache::put(self::key($userId), $map, now()->addDays(self::TTL_DAYS));
    }

    public static function isClosed(int $userId, string $uuid): bool
    {
        return isset(self::all($userId)[$uuid]);
    }

    /**
     * @return array<int, string>
     */
    public static function closedUuids(int $userId): array
    {
        return array_keys(self::all($userId));
    }

    public static function forget(int $userId, string $uuid): void
    {
        $map = self::all($userId);

        if (!isset($map[$uuid])) {
            return;
        }

        unset($map[$uuid]);

        Cache::put(self::key($userId), $map, now()->addDays(self::TTL_DAYS));
    }

    /**
     * @return array<string, int> uuid => closed at timestamp
     */
    private static function all(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $map = Cache::get(self::key($userId), []);

        return is_array($map) ? $map : [];
    }

    private static function key(int $userId): string
    {
        return 'drafts:closed:u' . $userId;
    }
}
