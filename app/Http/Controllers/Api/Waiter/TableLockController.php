<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\TableLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableLockController extends Controller
{
    public function lock(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        abort_unless((int) $table->branch_id === (int) $request->user()?->branch_id, 404);

        $lock = $tableLockService->lock(
            $table,
            'cashier',
            $request->user()?->id,
            (string) ($request->session()->get('active_staff_name') ?: $request->user()?->name),
        );

        return response()->json([
            'success' => true,
            'message' => 'Masa başarıyla kilitlendi.',
            'data' => [
                'table_id' => (int) $table->id,
                'is_locked' => true,
                'locked_by' => $lock['locked_by'],
            ],
        ]);
    }

    public function unlock(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        abort_unless((int) $table->branch_id === (int) $request->user()?->branch_id, 404);

        $tableLockService->unlock($table);

        return response()->json([
            'success' => true,
            'message' => 'Masa kilidi kaldırıldı.',
        ]);
    }
}
