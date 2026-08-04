<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DijiMenuIntegration;
use App\Models\Organization;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDijiMenuController extends Controller
{
    public function show(string $companySlug, string $branchSlug): View
    {
        $integration = DijiMenuIntegration::query()
            ->where('company_slug', $companySlug)
            ->first();

        abort_if($integration && ! $integration->is_active, 404);

        $organization = $integration?->organization
            ?? Organization::query()
                ->where('is_active', true)
                ->get()
                ->first(fn (Organization $candidate) => hash_equals(
                    Str::slug((string) ($candidate->code ?: $candidate->name)),
                    Str::slug($companySlug),
                ) || hash_equals(Str::slug((string) $candidate->name), Str::slug($companySlug)));
        abort_unless($organization, 404);

        $requestedSlug = Str::slug($branchSlug);
        $branchSlugs = $integration?->branch_slugs ?? [];
        $branch = $organization
            ->branches()
            ->where('branches.is_active', true)
            ->get()
            ->first(function (Branch $candidate) use ($branchSlugs, $requestedSlug): bool {
                $configuredSlug = $branchSlugs[(string) $candidate->id] ?? $branchSlugs[$candidate->id] ?? null;

                return ($configuredSlug && hash_equals(Str::slug((string) $configuredSlug), $requestedSlug))
                    || hash_equals(Str::slug((string) $candidate->code), $requestedSlug)
                    || hash_equals(Str::slug((string) $candidate->name), $requestedSlug);
            });
        abort_unless($branch, 404);

        $categories = Category::withoutGlobalScope('authenticated_branch')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('diji-menu.public', compact('branch', 'categories', 'organization'));
    }
}
