<?php
// app/Http/Controllers/ActivityLogController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/** Read-only audit trail (admin only). */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user')
            ->when($request->action, fn ($q, $a) => $q->where('action', $a))
            ->when($request->subject, fn ($q, $s) => $q->where('subject_type', $s))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('administrator.activity-log', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjects' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
