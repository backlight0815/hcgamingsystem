<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isGlobalSuperAdmin(), 403);

        $logs = DB::table('admin_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'admin_audit_logs.actor_id')
            ->select('admin_audit_logs.*', 'users.name as actor_name')
            ->orderByDesc('admin_audit_logs.id')->paginate(50);

        return $request->expectsJson() ? response()->json($logs) : view('admin.config.audit_log', compact('logs'));
    }
}
