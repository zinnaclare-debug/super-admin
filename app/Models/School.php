<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $fillable = [
        'name',
        'location',
        'username_prefix',
        'email',
        'contact_email',
        'contact_phone',
        'logo_path',
        'head_of_school_name',
        'head_signature_path',
        'assessment_schema',
        'grading_schema',
        'department_templates',
        'class_templates',
        'website_content',
        'entrance_exam_config',
        'slug',
        'subdomain',
        'status',
        'paystack_subaccount_code',
        'results_published',
    ];

    protected $casts = [
        'results_published' => 'boolean',
        'assessment_schema' => 'array',
        'grading_schema' => 'array',
        'department_templates' => 'array',
        'class_templates' => 'array',
        'website_content' => 'array',
        'entrance_exam_config' => 'array',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'school_admin');
    }

    public function admissionApplications(): HasMany
    {
        return $this->hasMany(SchoolAdmissionApplication::class);
    }
}
