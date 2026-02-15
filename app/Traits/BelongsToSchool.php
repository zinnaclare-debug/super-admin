<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    protected static function bootBelongsToSchool()
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->school_id)) {
                $model->school_id = auth()->user()->school_id;
            }
        });

        static::addGlobalScope('school', function (Builder $builder) {
            if (auth()->check() && auth()->user()->school_id) {
                $builder->where('school_id', auth()->user()->school_id);
            }
        });
    }
}
