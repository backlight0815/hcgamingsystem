<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import the SoftDeletes trait

class Product extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded =[];
    protected $table = 'product';


    public function productcategory(){
        return $this->belongsTo(ProductCategory::class,'product_category_id','id');

    }
// Product.php

    public function cart()
    {
        return $this->hasMany(Cart::class,'product_id');
    }
    public function orderItems()
    {
        return $this->hasMany(order_items::class, 'product_id');
    }
    public function category()
    {
        // Include withTrashed to retrieve soft deleted categories as well
        return $this->belongsTo(ProductCategory::class, 'category_id')->withTrashed();
    }

    public function dealerStocks()
    {
        return $this->hasOne(DealerStock::class, 'product_id','id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


}
