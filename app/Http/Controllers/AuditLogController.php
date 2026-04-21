<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->q, fn($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('path', 'ilike', "%{$s}%")
                  ->orWhere('user_name', 'ilike', "%{$s}%")
                  ->orWhere('action', 'ilike', "%{$s}%");
            }))
            ->when($request->method, fn($q,$m) => $q->where('method', $m))
            ->when($request->source, fn($q,$s) => $q->where('source', $s))
            ->when($request->user_id, fn($q,$u) => $q->where('user_id', $u))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs'    => $logs,
            'filters' => $request->only(['q','method','source','user_id']),
        ]);
    }
}
