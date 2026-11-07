<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\SiteContent;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SiteContent $siteContent): View
    {
        $projects = Project::query()
            ->published()
            ->with(['currentTerms', 'primaryImage', 'categoryRecord'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
        $homepage = $siteContent->homepage(app()->getLocale());
        $heroSlides = $homepage['slides'];

        if ($heroSlides === []) {
            $heroSlides = $projects->map(fn (Project $project): array => [
                'url' => $project->primaryImage?->url() ?? asset('images/projects/urban-commercial.svg'),
                'alt' => $project->displayName(),
            ])->all();
        }

        if ($heroSlides === []) {
            $heroSlides = [[
                'url' => asset('images/projects/urban-commercial.svg'),
                'alt' => __('site.hero.image_alt'),
            ]];
        }

        return view('home', [
            'projects' => $projects,
            'projectCount' => Project::query()->published()->count(),
            'homepage' => $homepage,
            'heroSlides' => $heroSlides,
        ]);
    }
}
