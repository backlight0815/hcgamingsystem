<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradingAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'broker',
        'account_number',
        'platform',
        'account_type',
        'currency',
        'starting_balance',
        'current_equity',
        'leverage',
        'status',
        'is_primary',
        'prop_firm_model_id',
        'notes',
    ];

    protected $casts = [
        'starting_balance' => 'decimal:2',
        'current_equity' => 'decimal:2',
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function propFirmModel()
    {
        return $this->belongsTo(PropFirmModel::class);
    }

    public function journals()
    {
        return $this->hasMany(TradingJournal::class);
    }

    public function capitals()
    {
        return $this->hasMany(Capital::class);
    }
}
