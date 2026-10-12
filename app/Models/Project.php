<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expected_handover_at' => 'date',
            'is_sample' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function currentTerms(): HasOne
    {
        return $this->hasOne(ProjectTerm::class)->latestOfMany('version');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(ProjectTerm::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProjectImage::class)->where('is_primary', true);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn
            ? $this->name_bn
            : $this->name;
    }

    public function displayLocation(): string
    {
        return app()->getLocale() === 'bn' && $this->location_bn
            ? $this->location_bn
            : $this->location;
    }

    public function displayCategory(): string
    {
        $translation = match ($this->category) {
            'Residential' => 'site.categories.residential',
            'Hospitality' => 'site.categories.hospitality',
            'Commercial' => 'site.categories.commercial',
            'Land' => 'site.categories.land',
            'Mixed-use' => 'site.categories.mixed_use',
            default => null,
        };

        return $translation ? __($translation) : $this->category;
    }

    public function displayType(): string
    {
        $type = $this->project_type ?? $this->category;

        $translation = match ($type) {
            'Hotel' => 'site.categories.hotel',
            'Commercial' => 'site.categories.commercial',
            'Land' => 'site.categories.land',
            default => null,
        };

        return $translation ? __($translation) : $type;
    }

    public function displayDeveloper(): ?string
    {
        return app()->getLocale() === 'bn' && $this->developer_bn
            ? $this->developer_bn
            : $this->developer;
    }

    public function displaySummary(): ?string
    {
        return app()->getLocale() === 'bn' && $this->summary_bn
            ? $this->summary_bn
            : $this->summary;
    }

    public function displayDescription(): ?string
    {
        return app()->getLocale() === 'bn' && $this->description_bn
            ? $this->description_bn
            : $this->description;
    }
}
