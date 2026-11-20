<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\SiteContent;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function __invoke(SiteContent $siteContent): View
    {
        $homepage = $siteContent->homepage(app()->getLocale());
        $projects = Project::query()
            ->published()
            ->with(['currentTerms', 'primaryImage', 'categoryRecord'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
        $fallbackImage = asset('images/projects/coastal-residences.svg');
        $projectImage = $projects->first()?->primaryImage?->url() ?? $fallbackImage;

        return view('about', [
            'homepage' => $homepage,
            'projects' => $projects,
            'projectCount' => Project::query()->published()->count(),
            'heroImageUrl' => $homepage['slides'][0]['url'] ?? $projectImage,
            'storyImageUrl' => $projectImage,
        ]);
    }
}
