<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'name',
        'location',
        'username_prefix',
        'email',
        'logo_path',
        'head_of_school_name',
        'head_signature_path',
        'slug',
        'subdomain',
        'status',
        'paystack_subaccount_code',
        'results_published',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(SchoolFeature::class);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->features()
            ->where('feature', $feature)
            ->where('enabled', true)
            ->exists();
    }
    public function users()
{
    return $this->hasMany(User::class);
}

public function admin()
{
    return $this->hasOne(User::class)->where('role', 'school_admin');
}





}
