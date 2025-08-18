<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import the SoftDeletes trait

use App\Models\DealerProductCategory;
class DealerStock extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded =[];
    protected $table = 'dealer_stock';



    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id','id');
    }

    public function dealerproductcategory(){
        return $this->belongsTo(DealerProductCategory::class,'product_category_id','id');

    }

    public function productcategory(){
        return $this->belongsTo(ProductCategory::class,'product_category_id','id');

    }
// Product.php

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

public function cart()
{
    return $this->hasMany(Cart::class, 'product_id', 'id');
}
public function dealerCarts()
{
    return $this->hasMany(DealerCart::class, 'dealer_stock_id', 'id');
}
}
