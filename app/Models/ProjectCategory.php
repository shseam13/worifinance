<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCategory extends Model
{
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'category', 'name');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn
            ? $this->name_bn
            : $this->name;
    }
}
