<?php

namespace App\Services;

use App\Support\PaymentMethods;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashShiftService
{
    /**
     * @var array<string, string>
     */
    public const DENOMINATIONS = [
        '20000' => '200 TL',
        '10000' => '100 TL',
        '5000' => '50 TL',
        '2000' => '20 TL',
        '1000' => '10 TL',
        '500' => '5 TL',
        '100' => '1 TL',
        '50' => '50 Kuruş',
        '25' => '25 Kuruş',
        '10' => '10 Kuruş',
        '5' => '5 Kuruş',
    ];

    public function openShift(User $user, float $openingCash, ?string $note): CashShift
    {
        $branchId = (int) $user->branch_id;

        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'opening_cash' => 'Kasa vardiyası açmak için kullanıcı bir şubeye bağlı olmalıdır.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $branchId, $openingCash, $note): CashShift {
                Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();

                if (CashShift::forBranch($branchId)->where('status', 'open')->exists()) {
                    throw ValidationException::withMessages([
                        'opening_cash' => 'Bu şubede zaten açık bir kasa vardiyası bulunuyor.',
                    ]);
                }

                return CashShift::create([
                    'branch_id' => $branchId,
                    'open_branch_key' => $branchId,
                    'opened_by_user_id' => $user->id,
                    'opened_by_staff_profile_id' => $this->staffId(),
                    'shift_number' => 'KASA-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'status' => 'open',
                    'opened_by_name' => $this->actorName($user),
                    'opening_cash' => round($openingCash, 2),
                    'opening_note' => $note,
                    'opened_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'opening_cash' => 'Bu şubede zaten açık bir kasa vardiyası bulunuyor.',
                ]);
            }

            throw $exception;
        }
    }

    public function addMovement(CashShift $shift, User $user, string $type, float $amount, string $reason): CashMovement
    {
        return DB::transaction(function () use ($shift, $user, $type, $amount, $reason): CashMovement {
            $lockedShift = CashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($lockedShift);

            $roundedAmount = round($amount, 2);
            if ($type === 'cash_out' && $roundedAmount > $this->calculateSummary($lockedShift)['expected_cash']) {
                throw ValidationException::withMessages([
                    'amount' => 'Kasadan çıkan tutar beklenen mevcut nakitten fazla olamaz.',
                ]);
            }

            return CashMovement::create([
                'cash_shift_id' => $lockedShift->id,
                'branch_id' => $lockedShift->branch_id,
                'created_by_user_id' => $user->id,
                'created_by_staff_profile_id' => $this->staffId(),
                'created_by_name' => $this->actorName($user),
                'type' => $type,
                'amount' => $roundedAmount,
                'reason' => trim($reason),
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, int>  $denominationCounts
     */
    public function closeShift(
        CashShift $shift,
        User $user,
        array $denominationCounts,
        float $otherAmount,
        ?string $closingNote
    ): CashShift {
        return DB::transaction(function () use ($shift, $user, $denominationCounts, $otherAmount, $closingNote): CashShift {
            $lockedShift = CashShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($lockedShift);

            $closedAt = now();
            $summary = $this->calculateSummary($lockedShift, $closedAt);
            $normalizedCounts = $this->normalizeDenominationCounts($denominationCounts);
            $countedCash = $this->countCash($normalizedCounts, $otherAmount);
            $difference = round($countedCash - $summary['expected_cash'], 2);

            if (abs($difference) >= 0.01 && blank($closingNote)) {
                throw ValidationException::withMessages([
                    'closing_note' => 'Kasa sayımında fark olduğu için açıklama yazılmalıdır.',
                ]);
            }

            $lockedShift->update([
                'open_branch_key' => null,
                'closed_by_user_id' => $user->id,
                'closed_by_staff_profile_id' => $this->staffId(),
                'closed_by_name' => $this->actorName($user),
                'status' => 'closed',
                'cash_sales' => $summary['cash_sales'],
                'cash_in_total' => $summary['cash_in_total'],
                'cash_out_total' => $summary['cash_out_total'],
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => $countedCash,
                'difference' => $difference,
                'payment_totals' => $summary['payment_totals'],
                'denomination_counts' => [
                    'counts' => $normalizedCounts,
                    'other_amount' => round($otherAmount, 2),
                ],
                'closing_note' => $closingNote,
                'closed_at' => $closedAt,
            ]);

            return $lockedShift->fresh();
        });
    }

    /**
     * @return array{
     *     payment_totals: array<string, float>,
     *     cash_sales: float,
     *     cash_in_total: float,
     *     cash_out_total: float,
     *     expected_cash: float
     * }
     */
    public function summary(CashShift $shift): array
    {
        if (! $shift->isOpen() && $shift->payment_totals !== null) {
            return [
                'payment_totals' => array_map('floatval', $shift->payment_totals),
                'cash_sales' => (float) $shift->cash_sales,
                'cash_in_total' => (float) $shift->cash_in_total,
                'cash_out_total' => (float) $shift->cash_out_total,
                'expected_cash' => (float) $shift->expected_cash,
            ];
        }

        return $this->calculateSummary($shift);
    }

    /**
     * @return array{
     *     payment_totals: array<string, float>,
     *     cash_sales: float,
     *     cash_in_total: float,
     *     cash_out_total: float,
     *     expected_cash: float
     * }
     */
    private function calculateSummary(CashShift $shift, ?CarbonInterface $until = null): array
    {
        $until ??= now();

        $paymentTotals = Payment::forBranch((int) $shift->branch_id)
            ->whereBetween('created_at', [$shift->opened_at, $until])
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn (mixed $total): float => round((float) $total, 2))
            ->all();

        $paymentTotals = array_replace(
            array_fill_keys(array_keys(PaymentMethods::catalog()), 0.0),
            $paymentTotals,
        );

        $movementTotals = CashMovement::forBranch((int) $shift->branch_id)
            ->where('cash_shift_id', $shift->id)
            ->where('occurred_at', '<=', $until)
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $cashSales = round((float) $paymentTotals['nakit'], 2);
        $cashIn = round((float) ($movementTotals['cash_in'] ?? 0), 2);
        $cashOut = round((float) ($movementTotals['cash_out'] ?? 0), 2);
        $expected = round((float) $shift->opening_cash + $cashSales + $cashIn - $cashOut, 2);

        return [
            'payment_totals' => $paymentTotals,
            'cash_sales' => $cashSales,
            'cash_in_total' => $cashIn,
            'cash_out_total' => $cashOut,
            'expected_cash' => $expected,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function normalizeDenominationCounts(array $counts): array
    {
        $normalized = [];

        foreach (self::DENOMINATIONS as $value => $label) {
            $normalized[(string) $value] = max(0, (int) ($counts[(string) $value] ?? 0));
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function countCash(array $counts, float $otherAmount): float
    {
        $total = max(0, $otherAmount);

        foreach ($counts as $denominationInKurus => $quantity) {
            $total += ((float) $denominationInKurus / 100) * $quantity;
        }

        return round($total, 2);
    }

    private function ensureOpen(CashShift $shift): void
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'Bu kasa vardiyası daha önce kapatılmış.',
            ]);
        }
    }

    private function staffId(): ?int
    {
        $staffId = request()->session()->get('active_staff_id');

        return is_numeric($staffId) ? (int) $staffId : null;
    }

    private function actorName(User $user): string
    {
        return (string) (request()->session()->get('active_staff_name') ?: $user->name);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
