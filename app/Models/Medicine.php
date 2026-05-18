<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit_type_id', 'price_hotline', 'price_contract', 'price_clinic'];

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
