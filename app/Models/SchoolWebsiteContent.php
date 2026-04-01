<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolWebsiteContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'heading',
        'content',
        'image_paths',
    ];

    protected $casts = [
        'image_paths' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
