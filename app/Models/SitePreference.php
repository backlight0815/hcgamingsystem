<?php

namespace App\Models;

use App\Support\FrontendExperience;
use Illuminate\Database\Eloquent\Model;

class SitePreference extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => FrontendExperience::forgetCache());
        static::deleted(fn () => FrontendExperience::forgetCache());
    }
}
