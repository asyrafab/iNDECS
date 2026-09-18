<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $base = Application::query()->pkm();

        $metrics = [
            'total' => (clone $base)->count(),
            'submitted' => (clone $base)->where('current_status', ApplicationStatus::Submitted)->count(),
            'in_review' => (clone $base)->where('current_status', ApplicationStatus::InReview)->count(),
            'approved' => (clone $base)->where('current_status', ApplicationStatus::Approved)->count(),
        ];

        $recentApplications = Application::query()
            ->pkm()
            ->with(['pkm', 'sites'])
            ->latest('submission_date')
            ->latest('created_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact('metrics', 'recentApplications'));
    }
}
