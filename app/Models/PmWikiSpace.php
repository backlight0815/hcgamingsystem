<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmWikiSpace extends Model
{
    use SoftDeletes;

    protected $table = 'pm_wiki_spaces';
    protected $guarded = ['id'];

    public function pages() { return $this->hasMany(PmWikiPage::class, 'space_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
}
