<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()->with('user')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('audit-logs.show', ['log' => $auditLog->load('user')]);
    }
}
