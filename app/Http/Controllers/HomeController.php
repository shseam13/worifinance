<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'projects' => Project::query()
                ->published()
                ->with(['currentTerms', 'primaryImage'])
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
            'projectCount' => Project::query()->published()->count(),
        ]);
    }
}
