<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('waiter.branch.{branchId}', function ($user, int $branchId): bool {
    return ! $user->isAdminUser() && (int) $user->branch_id === $branchId;
});
