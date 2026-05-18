<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispensedMedicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'medicine_id',
        'referral_number',
        'dispense_date',
        'quantity'
    ];

    protected $casts = [
        'dispense_date' => 'date',
        'quantity' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
