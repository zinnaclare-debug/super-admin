<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSchool;
use App\Scopes\SchoolScope;

class SchoolFeature extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'feature',
        'enabled',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
    protected static function booted()
{
    static::addGlobalScope(new SchoolScope);
}
}
