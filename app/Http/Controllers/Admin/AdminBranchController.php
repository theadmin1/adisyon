<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::withCount(['licenses', 'devices'])->latest()->paginate(15);
        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code',
            'contact_email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        Branch::create($validated);

        return redirect()->back()->with('success', 'Yeni Şube Başarıyla Eklendi!');
    }
}
