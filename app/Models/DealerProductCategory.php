<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerProductCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dealer_product_category';
    // protected $primaryKey = 'id';
  protected $fillable =[
        'id',
        'name',

    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
