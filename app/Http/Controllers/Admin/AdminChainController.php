<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminChainController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::with(['branches:id,name,code', 'users.chainBranches:id,name,code'])
            ->withCount(['branches', 'users'])
            ->orderBy('name')
            ->get();
        $branches = Branch::with('organizations:id,name')->orderBy('name')->get();

        return view('admin.chains.index', compact('organizations', 'branches'));
    }

    public function storeOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:organizations,code'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);
        $this->ensureBranchesAreAvailable($validated['branch_ids'] ?? []);

        DB::transaction(function () use ($validated): void {
            $organization = Organization::create([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'is_active' => true,
            ]);
            $organization->branches()->sync($validated['branch_ids'] ?? []);
        });

        return back()->with('success', 'Zincir oluşturuldu.');
    }

    public function updateOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('organizations', 'code')->ignore($organization)],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $this->ensureBranchesAreAvailable($validated['branch_ids'] ?? [], $organization);

        DB::transaction(function () use ($organization, $validated, $request): void {
            $organization->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'is_active' => $request->boolean('is_active'),
            ]);
            $organization->branches()->sync($validated['branch_ids'] ?? []);
        });

        return back()->with('success', 'Zincir ve bağlı şubeler güncellendi.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $organization = Organization::findOrFail($validated['organization_id']);
        $branchIds = $this->validatedOrganizationBranches($organization, $validated['branch_ids'] ?? []);

        DB::transaction(function () use ($validated, $organization, $branchIds): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'organization_id' => $organization->id,
                'chain_role' => $validated['chain_role'],
                'branch_id' => null,
                'restaurant_id' => null,
                'is_admin' => false,
            ]);
            $user->chainBranches()->sync($branchIds);
        });

        return back()->with('success', 'Zincir paneli kullanıcısı oluşturuldu.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isChainUser(), 404);

        $validated = $this->validateUser($request, $user);
        $organization = Organization::findOrFail($validated['organization_id']);
        $branchIds = $this->validatedOrganizationBranches($organization, $validated['branch_ids'] ?? []);

        DB::transaction(function () use ($validated, $organization, $branchIds, $user): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'organization_id' => $organization->id,
                'chain_role' => $validated['chain_role'],
                'branch_id' => null,
                'is_admin' => false,
            ];
            if (! empty($validated['password'])) {
                $attributes['password'] = Hash::make($validated['password']);
            }
            $user->update($attributes);
            $user->chainBranches()->sync($branchIds);
        });

        return back()->with('success', 'Zincir kullanıcısı ve şube erişimleri güncellendi.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        abort_unless($user->isChainUser(), 404);
        $user->delete();

        return back()->with('success', 'Zincir paneli kullanıcısı silindi.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:10', 'max:255'],
            'chain_role' => ['required', Rule::in(['owner', 'general_manager', 'regional_manager', 'analyst'])],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
        ]);
    }

    private function validatedOrganizationBranches(Organization $organization, array $requested): array
    {
        if ($requested === []) {
            return [];
        }

        $valid = $organization->branches()->whereIn('branches.id', $requested)->pluck('branches.id')->all();
        abort_unless(count($valid) === count(array_unique($requested)), 422, 'Seçilen şubeler bu zincire bağlı değil.');

        return $valid;
    }

    private function ensureBranchesAreAvailable(array $branchIds, ?Organization $organization = null): void
    {
        $conflict = Branch::whereIn('id', $branchIds)
            ->whereHas('organizations', function ($query) use ($organization): void {
                if ($organization) {
                    $query->where('organizations.id', '!=', $organization->id);
                }
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Bir şube aynı anda yalnızca bir zincire bağlanabilir.',
            ]);
        }
    }
}
