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

        $actorId = $request->session()->get('active_staff_id');
        $actorName = (string) ($request->session()->get('active_staff_name') ?: $request->user()?->name);
        $lock = $tableLockService->acquire(
            $table,
            'cashier',
            is_numeric($actorId) ? (int) $actorId : $request->user()?->id,
            $actorName,
        );

        if (($lock['conflict'] ?? false) === true) {
            return response()->json([
                'success' => false,
                'message' => trim(($lock['actor_name'] ?? 'Başka bir personel').' bu masada işlem yapıyor.'),
                'code' => 'TABLE_LOCKED',
                'data' => [
                    'table_id' => (int) $table->id,
                    'is_locked' => true,
                    'locked_by' => $lock['locked_by'] ?? null,
                    'actor_name' => $lock['actor_name'] ?? null,
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Masa başarıyla kilitlendi.',
            'data' => [
                'table_id' => (int) $table->id,
                'is_locked' => true,
                'locked_by' => $lock['locked_by'],
                'actor_name' => $lock['actor_name'] ?? null,
            ],
        ]);
    }

    public function unlock(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        abort_unless((int) $table->branch_id === (int) $request->user()?->branch_id, 404);

        $actorId = $request->session()->get('active_staff_id');
        $tableLockService->releaseIfOwnedBy(
            $table,
            'cashier',
            is_numeric($actorId) ? (int) $actorId : $request->user()?->id,
            (string) ($request->session()->get('active_staff_name') ?: $request->user()?->name),
        );

        return response()->json([
            'success' => true,
            'message' => 'Masa kilidi kaldırıldı.',
        ]);
    }
}
