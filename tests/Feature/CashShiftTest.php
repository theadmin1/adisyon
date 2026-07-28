<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Check;
use App\Models\Payment;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_only_one_shift_per_branch(): void
    {
        [$branch, $user, $staff] = $this->identity('A');

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), [
                'opening_cash' => 250.50,
                'opening_note' => 'Sabah açılışı',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cash_shifts', [
            'branch_id' => $branch->id,
            'status' => 'open',
            'opening_cash' => 250.50,
            'opened_by_staff_profile_id' => $staff->id,
            'open_branch_key' => $branch->id,
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 100])
            ->assertSessionHasErrors('opening_cash');

        $this->assertDatabaseCount('cash_shifts', 1);
    }

    public function test_expected_cash_combines_sales_and_manual_movements(): void
    {
        [$branch, $user, $staff] = $this->identity('B');
        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 100])
            ->assertRedirect();

        $shift = CashShift::firstOrFail();
        $check = $this->check($branch, $user, 'CASH-EXPECTED');
        Payment::create([
            'branch_id' => $branch->id,
            'check_id' => $check->id,
            'payment_method' => 'nakit',
            'amount' => 50,
            'sync_uuid' => (string) Str::uuid(),
        ]);
        Payment::create([
            'branch_id' => $branch->id,
            'check_id' => $check->id,
            'payment_method' => 'kredi_karti',
            'amount' => 75,
            'sync_uuid' => (string) Str::uuid(),
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.movements.store', $shift), [
                'type' => 'cash_in',
                'amount' => 20,
                'reason' => 'Bozuk para girişi',
            ])
            ->assertRedirect();

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.movements.store', $shift), [
                'type' => 'cash_out',
                'amount' => 10,
                'reason' => 'Kurye avansı',
            ])
            ->assertRedirect();

        $this->actingAsStaff($user, $staff)
            ->get(route('cash-shifts.index'))
            ->assertOk()
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['cash_sales'] === 50.0
                    && $summary['cash_in_total'] === 20.0
                    && $summary['cash_out_total'] === 10.0
                    && $summary['expected_cash'] === 160.0
                    && $summary['payment_totals']['kredi_karti'] === 75.0;
            });
    }

    public function test_cash_out_cannot_exceed_expected_cash(): void
    {
        [, $user, $staff] = $this->identity('C');
        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 100])
            ->assertRedirect();

        $shift = CashShift::firstOrFail();

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.movements.store', $shift), [
                'type' => 'cash_out',
                'amount' => 100.01,
                'reason' => 'Fazla çıkış denemesi',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_difference_requires_note_and_exact_count_closes_shift(): void
    {
        [$branch, $user, $staff] = $this->identity('D');
        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 100])
            ->assertRedirect();

        $shift = CashShift::firstOrFail();
        $check = $this->check($branch, $user, 'CASH-CLOSE');
        Payment::create([
            'branch_id' => $branch->id,
            'check_id' => $check->id,
            'payment_method' => 'nakit',
            'amount' => 50,
            'sync_uuid' => (string) Str::uuid(),
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.close', $shift), [
                'denominations' => ['100' => 140],
                'other_amount' => 0,
            ])
            ->assertSessionHasErrors('closing_note');

        $this->assertSame('open', $shift->fresh()->status);

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.close', $shift), [
                'denominations' => ['100' => 150],
                'other_amount' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $closed = $shift->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertNull($closed->open_branch_key);
        $this->assertSame('150.00', $closed->expected_cash);
        $this->assertSame('150.00', $closed->counted_cash);
        $this->assertSame('0.00', $closed->difference);
        $this->assertSame(150, $closed->denomination_counts['counts']['100']);

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 25])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_shifts', 2);
    }

    public function test_other_branch_cannot_access_a_cash_shift(): void
    {
        [, $userA, $staffA] = $this->identity('E');
        [, $userB, $staffB] = $this->identity('F');

        $this->actingAsStaff($userA, $staffA)
            ->post(route('cash-shifts.store'), ['opening_cash' => 100])
            ->assertRedirect();

        $shiftA = CashShift::withoutGlobalScopes()->firstOrFail();

        $this->actingAsStaff($userB, $staffB)
            ->post(route('cash-shifts.movements.store', $shiftA->id), [
                'type' => 'cash_in',
                'amount' => 10,
                'reason' => 'Yetkisiz hareket',
            ])
            ->assertNotFound();
    }

    public function test_cash_actions_are_written_to_audit_trail(): void
    {
        [, $user, $staff] = $this->identity('G');
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.store'), ['opening_cash' => 10])
            ->assertRedirect();

        $shift = CashShift::firstOrFail();

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.movements.store', $shift), [
                'type' => 'cash_in',
                'amount' => 5,
                'reason' => 'Test girişi',
            ])
            ->assertRedirect();

        $this->actingAsStaff($user, $staff)
            ->post(route('cash-shifts.close', $shift), [
                'denominations' => ['100' => 15],
                'other_amount' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_shift.opened', 'category' => 'cash']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_shift.cash_added', 'category' => 'cash']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_shift.closed', 'category' => 'cash']);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function identity(string $suffix): array
    {
        $branch = Branch::create([
            'name' => "Kasa Şubesi {$suffix}",
            'code' => "CASH-{$suffix}",
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Kasiyer {$suffix}",
            'email' => "cash-{$suffix}@example.test",
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => "Kasa Personeli {$suffix}",
            'role' => 'Kasa',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'teal',
            'is_active' => true,
        ]);

        return [$branch, $user, $staff];
    }

    private function actingAsStaff(User $user, StaffProfile $staff): static
    {
        return $this->actingAs($user)->withSession([
            'active_staff_id' => $staff->id,
            'active_staff_name' => $staff->name,
            'active_staff_role' => $staff->role,
            'active_staff_color' => $staff->avatar_color,
        ]);
    }

    private function check(Branch $branch, User $user, string $number): Check
    {
        return Check::create([
            'branch_id' => $branch->id,
            'waiter_id' => $user->id,
            'check_number' => $number,
            'sync_uuid' => (string) Str::uuid(),
            'status' => CheckStatus::Closed,
            'total' => 125,
            'opened_at' => now(),
            'closed_at' => now(),
        ]);
    }
}
