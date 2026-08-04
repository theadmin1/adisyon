<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Services\AuditLogger;
use App\Services\CashShiftService;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashShiftController extends Controller
{
    public function index(Request $request, CashShiftService $cashShiftService): View
    {
        $currentShift = CashShift::query()
            ->where('status', 'open')
            ->with(['movements' => fn ($query) => $query->latest('occurred_at')->latest('id')])
            ->latest('opened_at')
            ->first();

        $summary = $currentShift ? $cashShiftService->summary($currentShift) : null;
        $history = CashShift::query()
            ->where('status', 'closed')
            ->latest('closed_at')
            ->paginate(20)
            ->withQueryString();

        return view('cash-shifts.index', [
            'currentShift' => $currentShift,
            'summary' => $summary,
            'history' => $history,
            'denominations' => CashShiftService::DENOMINATIONS,
            'paymentMethods' => PaymentMethods::catalog(),
        ]);
    }

    public function store(
        Request $request,
        CashShiftService $cashShiftService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $cashShiftService->openShift(
            $request->user(),
            (float) $validated['opening_cash'],
            $validated['opening_note'] ?? null,
        );

        $auditLogger->record(
            action: 'cash_shift.opened',
            subject: $shift,
            newValues: [
                'shift_number' => $shift->shift_number,
                'opening_cash' => $shift->opening_cash,
            ],
            description: 'Kasa vardiyası açıldı.',
            category: 'cash',
        );

        return back()->with('success', "Kasa vardiyası açıldı: {$shift->shift_number}");
    }

    public function movement(
        Request $request,
        CashShift $cashShift,
        CashShiftService $cashShiftService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['required', 'in:cash_in,cash_out'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $movement = $cashShiftService->addMovement(
            $cashShift,
            $request->user(),
            $validated['type'],
            (float) $validated['amount'],
            $validated['reason'],
        );

        $auditLogger->record(
            action: $movement->type === 'cash_in' ? 'cash_shift.cash_added' : 'cash_shift.cash_removed',
            subject: $cashShift,
            newValues: [
                'movement_id' => $movement->id,
                'type' => $movement->type,
                'amount' => $movement->amount,
                'reason' => $movement->reason,
            ],
            description: $movement->type === 'cash_in' ? 'Kasaya manuel nakit girişi yapıldı.' : 'Kasadan manuel nakit çıkışı yapıldı.',
            category: 'cash',
        );

        return back()->with('success', 'Kasa hareketi kaydedildi.');
    }

    public function close(
        Request $request,
        CashShift $cashShift,
        CashShiftService $cashShiftService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $rules = [
            'denominations' => ['required', 'array'],
            'other_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ];

        foreach (CashShiftService::DENOMINATIONS as $value => $label) {
            $rules["denominations.{$value}"] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        $validated = $request->validate($rules);
        $closedShift = $cashShiftService->closeShift(
            $cashShift,
            $request->user(),
            $validated['denominations'],
            (float) ($validated['other_amount'] ?? 0),
            $validated['closing_note'] ?? null,
        );

        $auditLogger->record(
            action: 'cash_shift.closed',
            subject: $closedShift,
            oldValues: ['status' => 'open'],
            newValues: [
                'status' => 'closed',
                'cash_sales' => $closedShift->cash_sales,
                'expected_cash' => $closedShift->expected_cash,
                'counted_cash' => $closedShift->counted_cash,
                'difference' => $closedShift->difference,
            ],
            description: 'Kasa vardiyası sayım yapılarak kapatıldı.',
            category: 'cash',
        );

        return back()->with('success', 'Kasa vardiyası kapatıldı. Sayım farkı: ₺'.number_format((float) $closedShift->difference, 2, ',', '.'));
    }
}
