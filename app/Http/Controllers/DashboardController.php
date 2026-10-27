<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $base = Application::query()->pkm()->visibleTo($request->user());

        $metrics = [
            'total' => (clone $base)->count(),
            'submitted' => (clone $base)->where('current_status', ApplicationStatus::Submitted)->count(),
            'in_review' => (clone $base)->where('current_status', ApplicationStatus::InReview)->count(),
            'approved' => (clone $base)->where('current_status', ApplicationStatus::Approved)->count(),
        ];

        $recentApplications = Application::query()
            ->pkm()
            ->visibleTo($request->user())
            ->with(['pkm', 'sites'])
            ->latest('submission_date')
            ->latest('created_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact('metrics', 'recentApplications'));
    }
}
