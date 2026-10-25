<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmWikiPage extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'wiki_page'; }

    protected $table = 'pm_wiki_pages';
    protected $guarded = ['id'];
    protected $casts = ['tags' => 'array', 'published_at' => 'datetime', 'status_code' => 'integer'];

    public const CATEGORIES = [
        'PGOS Product Knowledge',
        'QA Training & Best Practices',
        'PMS User Guide',
        'Ticket Management',
        'Project & Release Management',
        'Team Collaboration',
        'Technical Reference',
    ];

    public function space() { return $this->belongsTo(PmWikiSpace::class, 'space_id'); }
    public function project() { return $this->belongsTo(PmProject::class, 'project_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('title'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function editor() { return $this->belongsTo(User::class, 'last_editor_id'); }
    public function getSafeHtmlAttribute(): string
    {
        return self::renderContent($this->content ?? '');
    }

    public static function renderContent(string $content): string
    {
        $token = 'PMSUNDERLINE'.Str::random(24);
        $content = preg_replace_callback('/\+\+([^\r\n]+?)\+\+/u',
            fn ($match) => $token.'START'.$match[1].$token.'END', $content);
        $html = Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return str_replace([$token.'START', $token.'END'], ['<u>', '</u>'], $html);
    }
}
