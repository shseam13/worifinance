<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $budgetOptions = [
            'under_5000' => 'site.projects.budget_under_5000',
            '5000_10000' => 'site.projects.budget_5000_10000',
            '10000_25000' => 'site.projects.budget_10000_25000',
            '25000_plus' => 'site.projects.budget_25000_plus',
        ];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', Rule::in(array_keys($budgetOptions))],
        ]);
        $search = trim($filters['q'] ?? '');

        $query = Project::query()
            ->published()
            ->with(['currentTerms', 'primaryImage', 'categoryRecord']);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('name_bn', 'like', $like);
            });
        }

        if (! empty($filters['budget'])) {
            $budget = $filters['budget'];
            $query->whereHas('currentTerms', function (Builder $query) use ($budget): void {
                match ($budget) {
                    'under_5000' => $query->where('unit_price_bdt', '<', 5000),
                    '5000_10000' => $query->where('unit_price_bdt', '>=', 5000)
                        ->where('unit_price_bdt', '<', 10000),
                    '10000_25000' => $query->where('unit_price_bdt', '>=', 10000)
                        ->where('unit_price_bdt', '<', 25000),
                    '25000_plus' => $query->where('unit_price_bdt', '>=', 25000),
                };
            });
        }

        $heroProject = Project::query()
            ->published()
            ->whereHas('primaryImage')
            ->with('primaryImage')
            ->orderByDesc('published_at')
            ->first();

        return view('projects.index', [
            'projects' => $query->orderByDesc('published_at')->paginate(9)->withQueryString(),
            'filters' => $filters,
            'budgetOptions' => $budgetOptions,
            'heroImageUrl' => $heroProject?->primaryImage?->url(),
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

        $project->load(['currentTerms', 'images', 'categoryRecord']);

        return view('projects.show', compact('project'));
    }
}
