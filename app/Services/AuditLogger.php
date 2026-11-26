<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(string $action, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null, ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
        ]);
    }
}
