<?php

namespace App\Services;

use App\Models\Check;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KitchenDispatchService
{
    public function __construct(private readonly PrintService $printService) {}

    /**
     * @return array{sent_count: int, print_queued: bool, print_error: ?string, sent_at: string}
     */
    public function send(Check $check): array
    {
        $unsentItems = $check->items()
            ->routedToKitchen()
            ->where('is_cancelled', false)
            ->where(function ($query): void {
                $query->whereNull('sent_to_kitchen_at')
                    ->orWhereIn('kitchen_status', ['pending', 'sent']);
            })
            ->get();

        $sentAt = now();
        DB::transaction(function () use ($check, $sentAt, $unsentItems): void {
            if ($unsentItems->isEmpty()) {
                return;
            }

            $check->update([
                'kitchen_sent_at' => $sentAt,
                'is_synced' => config('database.default') === 'mysql',
            ]);
            $check->items()
                ->routedToKitchen()
                ->where('is_cancelled', false)
                ->where(function ($query): void {
                    $query->whereNull('sent_to_kitchen_at')
                        ->orWhereIn('kitchen_status', ['pending', 'sent']);
                })
                ->update([
                    'kitchen_status' => 'received',
                    'sent_to_kitchen_at' => $sentAt,
                    'is_synced' => config('database.default') === 'mysql',
                    'updated_at' => $sentAt,
                ]);
        });

        $printQueued = false;
        $printError = null;
        if ($unsentItems->isNotEmpty() && Setting::get('auto_print_kitchen', '1') == '1') {
            try {
                $this->printService->createKitchenSlip($check, $unsentItems);
                $printQueued = true;
            } catch (\Throwable $exception) {
                $printError = $exception->getMessage();
                Log::error('Mutfak fişi kuyruğa alınamadı', [
                    'check_id' => $check->id,
                    'error' => $printError,
                ]);
            }
        }

        return [
            'sent_count' => $unsentItems->count(),
            'print_queued' => $printQueued,
            'print_error' => $printError,
            'sent_at' => $sentAt->toIso8601String(),
        ];
    }
}
