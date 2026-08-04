<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Check;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChainBranchController extends Controller
{
    public function index(): View
    {
        $branchIds = Auth::user()->accessibleChainBranchIds();
        $branches = Branch::whereIn('id', $branchIds)
            ->with([
                'halls' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'diningTables' => fn ($query) => $query->with('hall')->orderBy('hall_id')->orderBy('name'),
            ])
            ->withCount(['devices', 'staffProfiles', 'products', 'diningTables'])
            ->orderBy('name')
            ->get();

        $openChecks = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->whereIn('status', ['open', 'awaiting_payment'])
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $todaySales = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->where('status', 'closed')->whereDate('closed_at', today())
            ->selectRaw('branch_id, COALESCE(SUM(total), 0) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $onlineDevices = Device::whereIn('branch_id', $branchIds)->where('last_ping_at', '>=', now()->subMinutes(2))
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $lowStocks = Product::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)
            ->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $openShifts = CashShift::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)->where('status', 'open')
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');

        return view('chain.branches.index', compact('branches', 'openChecks', 'todaySales', 'onlineDevices', 'lowStocks', 'openShifts'));
    }

    public function storeTable(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        $validated = $request->validate([
            'hall_id' => ['nullable', 'integer', Rule::exists('halls', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'new_hall_name' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('dining_tables', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $hallId = $validated['hall_id'] ?? null;
        if (filled($validated['new_hall_name'] ?? null)) {
            $hall = Hall::withoutGlobalScopes()->firstOrCreate(
                ['branch_id' => $branch->id, 'name' => trim($validated['new_hall_name'])],
                [
                    'code' => mb_strtoupper(mb_substr(trim($validated['new_hall_name']), 0, 12)),
                    'sort_order' => ((int) Hall::withoutGlobalScopes()->where('branch_id', $branch->id)->max('sort_order')) + 1,
                    'is_active' => true,
                ]
            );
            $hallId = $hall->id;
        }

        DiningTable::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'hall_id' => $hallId,
            'name' => trim($validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim($validated['code']) : null,
            'capacity' => $validated['capacity'],
            'status' => 'available',
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', "{$branch->name} şubesine {$validated['name']} masası eklendi.");
    }

    public function updateTable(Request $request, Branch $branch, DiningTable $table): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        abort_unless((int) $table->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'hall_id' => ['nullable', 'integer', Rule::exists('halls', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('dining_tables', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))->ignore($table->id)],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $table->update([
            'hall_id' => $validated['hall_id'] ?? null,
            'name' => trim($validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim($validated['code']) : null,
            'capacity' => $validated['capacity'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ]);

        return back()->with('success', "{$table->name} masası güncellendi.");
    }

    public function storeHall(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('halls', 'name')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('halls', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))],
        ]);

        Hall::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'name' => trim($validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim($validated['code']) : null,
            'sort_order' => ((int) Hall::withoutGlobalScopes()->where('branch_id', $branch->id)->max('sort_order')) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', "{$validated['name']} masa kategorisi eklendi.");
    }

    public function updateHall(Request $request, Branch $branch, Hall $hall): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        abort_unless((int) $hall->branch_id === (int) $branch->id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('halls', 'name')->where(fn ($query) => $query->where('branch_id', $branch->id))->ignore($hall->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('halls', 'code')->where(fn ($query) => $query->where('branch_id', $branch->id))->ignore($hall->id)],
        ]);

        $hall->update([
            'name' => trim($validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim($validated['code']) : null,
        ]);

        return back()->with('success', 'Masa kategorisi güncellendi.');
    }

    public function destroyHall(Branch $branch, Hall $hall): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        abort_unless((int) $hall->branch_id === (int) $branch->id, 404);

        if ($hall->diningTables()->exists()) {
            return back()->withErrors(['hall' => 'İçinde masa bulunan kategori silinemez. Önce masaları başka kategoriye taşıyın veya silin.']);
        }

        $name = $hall->name;
        $hall->delete();

        return back()->with('success', "{$name} masa kategorisi silindi.");
    }

    public function toggleTable(Branch $branch, DiningTable $table): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        abort_unless((int) $table->branch_id === (int) $branch->id, 404);

        if ($table->is_active && $table->checks()->whereIn('status', ['open', 'awaiting_payment'])->exists()) {
            return back()->withErrors(['table' => 'Açık adisyonu bulunan masa pasife alınamaz.']);
        }

        $active = ! $table->is_active;
        $table->update(['is_active' => $active, 'status' => $active ? 'available' : 'inactive']);

        return back()->with('success', "{$table->name} masası ".($active ? 'aktifleştirildi.' : 'pasife alındı.'));
    }

    public function destroyTable(Branch $branch, DiningTable $table): RedirectResponse
    {
        $this->authorizeMutation();
        $this->authorizeBranch($branch);
        abort_unless((int) $table->branch_id === (int) $branch->id, 404);

        if ($table->checks()->exists()) {
            return back()->withErrors(['table' => 'Adisyon geçmişi bulunan masa silinemez; pasife alabilirsiniz.']);
        }

        $name = $table->name;
        $table->delete();

        return back()->with('success', "{$name} masası silindi.");
    }

    private function authorizeBranch(Branch $branch): void
    {
        abort_unless(in_array($branch->id, Auth::user()->accessibleChainBranchIds(), true), 403);
    }

    private function authorizeMutation(): void
    {
        abort_if(Auth::user()->chain_role === 'analyst', 403);
    }
}
