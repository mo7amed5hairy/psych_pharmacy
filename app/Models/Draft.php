<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Draft extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_DISCARDED = 'discarded';

    /**
     * Whitelisted draft types and their Arabic labels.
     * Adding a type here is the only way a client can store it.
     */
    public const TYPES = [
        'invoice' => 'فاتورة صرف',
        'dispensed_medicine' => 'أدوية منصرفة',
        'medicine' => 'إضافة أدوية',
        'medicine_edit' => 'تعديل دواء',
        'stock' => 'إضافة رصيد',
        'stock_edit' => 'تعديل رصيد',
        'stock_transfer' => 'تحويل أرصدة',
        'unit' => 'إضافة وحدة',
        'unit_edit' => 'تعديل وحدة',
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'title',
        'payload',
        'revision',
        'status',
        'finalized_at',
        'finalized_by',
        'result',
    ];

    protected $casts = [
        'payload' => 'array',
        'revision' => 'integer',
        'finalized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Best-effort human title built from the payload without extra queries.
     */
    public static function titleFor(string $type, array $payload): string
    {
        $str = static fn ($key) => isset($payload[$key]) && is_scalar($payload[$key])
            ? trim((string) $payload[$key])
            : '';

        switch ($type) {
            case 'invoice':
                $ref = $str('referral_number');
                return 'فاتورة ' . ($ref !== '' ? $ref : 'بدون رقم');

            case 'dispensed_medicine':
                $rows = isset($payload['medicines']) && is_array($payload['medicines'])
                    ? count($payload['medicines'])
                    : 0;
                return "صرفية ({$rows} صنف)";

            case 'medicine':
                if (isset($payload['medicines']) && is_array($payload['medicines'])) {
                    return 'إضافة ' . count($payload['medicines']) . ' دواء';
                }
                $name = $str('name');
                return 'إضافة دواء' . ($name !== '' ? ' — ' . $name : '');

            case 'medicine_edit':
                $name = $str('name');
                return 'تعديل دواء' . ($name !== '' ? ' — ' . $name : '');

            case 'stock':
                return 'إضافة رصيد دواء';

            case 'stock_edit':
                return 'تعديل رصيد دواء';

            case 'stock_transfer':
                $month = isset($payload['month']) ? (int) $payload['month'] : 0;
                $year = isset($payload['year']) ? (int) $payload['year'] : 0;
                return 'تحويل أرصدة' . ($month && $year ? " إلى {$month}/{$year}" : '');

            case 'unit':
                $name = $str('name');
                return 'إضافة وحدة' . ($name !== '' ? ' — ' . $name : '');

            case 'unit_edit':
                $name = $str('name');
                return 'تعديل وحدة' . ($name !== '' ? ' — ' . $name : '');
        }

        return self::TYPES[$type] ?? $type;
    }
}
