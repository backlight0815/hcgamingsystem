<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;
    protected $guarded =[];

    public function scopeStorefront($query)
    {
        return $query->where('blog_title', 'not like', '[Demo]%')
            ->where('blog_title', 'not like', '??%');
    }
    // protected $table = 'blogs';

    // protected $fillable = ['page_views']; // Add this line

    public function category(){
        return $this->belongsTo(BlogCategory::class,'blog_category_id','id');

    }


}
