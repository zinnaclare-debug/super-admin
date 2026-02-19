<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

protected $fillable = [
    'name',
    'email',
    'photo_path',
    'username',   
    'password',
    'role',
    'school_id',
];


    /**
     * Role constants (ADDED)
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_SCHOOL_ADMIN = 'school_admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_STUDENT = 'student';

    protected static function booted()
    {
        static::creating(function ($user) {

            if ($user->role === self::ROLE_SUPER_ADMIN) {
                $user->school_id = null;
                return;
            }

            if ($user->school_id) {
                return;
            }

            if (auth()->check()) {
                $user->school_id = auth()->user()->school_id;
            }
        });

        static::creating(function ($user) {
    if ($user->role === 'school_admin') {
        $exists = self::where('school_id', $user->school_id)
            ->where('role', 'school_admin')
            ->exists();

        if ($exists) {
            throw new \Exception('This school already has an admin');
        }
    }
});


    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Role helpers (ADDED)
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === self::ROLE_SCHOOL_ADMIN;
    }

 public function isStaff(): bool
{
    return $this->role === 'staff';
}


    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }


    public function staffProfile()
    {
        return $this->hasOne(\App\Models\Staff::class);
    }

    public function studentProfile()
    {
        return $this->hasOne(\App\Models\Student::class);
    }

//     public function admin()
// {
//     return $this->hasOne(User::class)
//         ->where('role', User::ROLE_SCHOOL_ADMIN);
// }


}
