<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::query()
                ->published()
                ->with(['currentTerms', 'primaryImage'])
                ->orderByDesc('published_at')
                ->paginate(9),
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless(
            $project->status === 'published'
                && $project->published_at !== null
                && $project->published_at->isPast(),
            404,
        );

        $project->load(['currentTerms', 'images']);

        return view('projects.show', compact('project'));
    }
}
