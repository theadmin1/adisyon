<?php

namespace App\Listeners;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RecordSuccessfulLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        try {
            $ipAddress = $this->request->ip();
            if (! is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                $ipAddress = null;
            }

            LoginLog::create([
                'user_id' => $event->user->getKey(),
                'branch_id' => $event->user->branch_id,
                'user_name' => $event->user->name,
                'user_email' => $event->user->email,
                'restaurant_id' => $event->user->restaurant_id,
                'portal' => $this->request->is('admin/*') ? 'admin' : 'restaurant',
                'guard' => $event->guard,
                'ip_address' => $ipAddress,
                'user_agent' => Str::limit((string) $this->request->userAgent(), 2000, ''),
                'remember_me' => $event->remember,
                'logged_in_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Başarılı kullanıcı girişi denetim kaydına yazılamadı.', [
                'user_id' => $event->user->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
