<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('system.activity-logs.index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->filter($request->query())
                ->latest('id')
                ->paginate(40)
                ->withQueryString(),
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'events' => ActivityLog::query()->select('event')->distinct()->orderBy('event')->pluck('event', 'event'),
        ]);
    }

    public function show(ActivityLog $activityLog): View
    {
        $activityLog->load('user');

        return view('system.activity-logs.show', ['log' => $activityLog]);
    }
}
