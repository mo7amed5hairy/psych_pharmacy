<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const DRAFTS_PURGE_ENABLED = 'drafts_auto_purge_enabled';
    public const DRAFTS_LAST_PURGE_AT = 'drafts_last_purge_at';

    public const STOCK_TRANSFER_ENABLED = 'stock_auto_transfer_enabled';
    public const STOCK_TRANSFER_NEXT_DUE = 'stock_transfer_next_due';
    public const STOCK_TRANSFER_LAST_RUN = 'stock_transfer_last_run_at';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Request-scoped memo so a page request reads each setting once.
     */
    protected static array $memo = [];

    public static function getValue(string $key): ?string
    {
        if (!array_key_exists($key, static::$memo)) {
            static::$memo[$key] = static::query()->where('key', $key)->value('value');
        }

        return static::$memo[$key];
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$memo[$key] = $value;
    }

    public static function enabled(string $key): bool
    {
        return static::getValue($key) === '1';
    }

    public static function forgetMemo(): void
    {
        static::$memo = [];
    }
}
