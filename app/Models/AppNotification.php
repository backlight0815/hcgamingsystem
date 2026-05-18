<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'user_id',
        'target_roles',
        'title',
        'message',
        'type',
        'action_url',
        'published_at',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AppNotificationRead::class);
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $roleId = (int) $user->role_id;

        return $query
            ->where(function (Builder $query) use ($user, $roleId): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere(function (Builder $query) use ($roleId): void {
                        $query
                            ->whereNull('user_id')
                            ->where(function (Builder $query) use ($roleId): void {
                                $query
                                    ->whereNull('target_roles')
                                    ->orWhereJsonContains('target_roles', $roleId)
                                    ->orWhereJsonContains('target_roles', (string) $roleId);
                            });
                    });
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function isReadBy(User $user): bool
    {
        if ($this->relationLoaded('reads')) {
            return $this->reads->contains('user_id', $user->id);
        }

        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
