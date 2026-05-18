<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralLinks extends Model
{
    use HasFactory;
    protected $table = 'referral_links'; // Set the table name if different from the model's name
    protected $guarded=[];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = Str::random($length);
        } while (
            static::where('referral_code', $code)->exists()
            || static::codeExistsOnUser($code)
        );

        return $code;
    }

    public static function ensureUniqueLink(int $roleId, int $userId, ?string $preferredCode = null): self
    {
        $link = static::firstOrNew([
            'role_id' => $roleId,
            'user_id' => $userId,
        ]);

        if ($link->exists && $link->referral_code) {
            return $link;
        }

        $code = $preferredCode;
        if (
            ! $code
            || static::where('referral_code', $code)->exists()
            || static::codeExistsOnUser($code, $userId)
        ) {
            $code = static::generateUniqueCode();
        }

        $link->referral_code = $code;
        $link->save();

        return $link;
    }

    private static function codeExistsOnUser(string $code, ?int $exceptUserId = null): bool
    {
        $query = User::where(function ($query) use ($code): void {
            $query->where('referral_code', $code)
                ->orWhere('customer_referral_code', $code)
                ->orWhere('signal_provider_referral_code', $code);
        });

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }
}
