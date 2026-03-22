<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public static function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createOtp($email)
    {
        self::where('email', $email)->delete();
        
        return self::create([
            'email' => $email,
            'otp' => self::generateOtp(),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public static function findValidOtp($email, $otp)
    {
        $reset = self::where('email', $email)
            ->where('otp', $otp)
            ->first();

        if (!$reset || $reset->isExpired()) {
            return null;
        }

        return $reset;
    }

    public static function deleteUsedOtp($email, $otp)
    {
        self::where('email', $email)
            ->where('otp', $otp)
            ->delete();
    }
}