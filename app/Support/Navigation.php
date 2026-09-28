<?php

namespace App\Support;

use App\Models\ServiceTicket;
use App\Models\Task;
use App\Models\User;

/**
 * Role-aware sidebar: modules a role can't use are hidden rather than
 * leading to error pages.
 */
class Navigation
{
    public function for(User $user): array
    {
        $sections = [
            '' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home', 'can' => 'dashboard.view'],
                ['label' => 'My Day', 'route' => 'my-day', 'active' => 'my-day', 'icon' => 'sun', 'can' => 'tasks.view', 'badge' => fn () => $this->myDayCount($user)],
            ],
            'Sales' => [
                ['label' => 'Customers', 'route' => 'customers.index', 'active' => 'customers.*', 'icon' => 'users', 'can' => 'customers.view'],
                ['label' => 'Pipeline', 'route' => 'pipeline', 'active' => ['pipeline', 'quotes.*'], 'icon' => 'view-columns', 'can' => 'pipeline.view'],
                ['label' => 'Bookings', 'route' => 'bookings.index', 'active' => 'bookings.*', 'icon' => 'ticket', 'can' => 'pipeline.view'],
            ],
            'Service' => [
                ['label' => 'Tickets', 'route' => 'tickets.index', 'active' => 'tickets.*', 'icon' => 'lifebuoy', 'can' => 'tickets.view', 'badge' => fn () => ServiceTicket::unresolved()->count() ?: null],
                ['label' => 'Feedback', 'route' => 'feedback.index', 'active' => 'feedback.index', 'icon' => 'chat-bubble-bottom-center-text', 'can' => 'feedback.view'],
            ],
            'Marketing' => [
                ['label' => 'Segments', 'route' => 'segments.index', 'active' => 'segments.*', 'icon' => 'funnel', 'can' => 'marketing.view'],
                ['label' => 'Campaigns', 'route' => 'campaigns.index', 'active' => 'campaigns.*', 'icon' => 'megaphone', 'can' => 'marketing.view'],
            ],
            'Insights' => [
                ['label' => 'Report builder', 'route' => 'reports.builder', 'active' => 'reports.builder', 'icon' => 'chart-bar-square', 'can' => 'reports.view'],
                ['label' => 'Scheduled reports', 'route' => 'reports.scheduled', 'active' => 'reports.scheduled', 'icon' => 'calendar-days', 'can' => 'reports.view'],
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'active' => 'suppliers.*', 'icon' => 'building-office-2', 'can' => 'suppliers.view'],
            ],
            'Governance' => [
                ['label' => 'Staff & roles', 'route' => 'admin.users', 'active' => 'admin.users', 'icon' => 'user-group', 'can' => 'admin.users'],
                ['label' => 'Audit trail', 'route' => 'admin.audit', 'active' => 'admin.audit', 'icon' => 'finger-print', 'can' => 'admin.audit'],
                ['label' => 'Backups', 'route' => 'admin.backups', 'active' => 'admin.backups', 'icon' => 'circle-stack', 'can' => 'admin.backup'],
                ['label' => 'Appearance', 'route' => 'admin.appearance', 'active' => 'admin.appearance', 'icon' => 'swatch', 'can' => 'settings.manage'],
                ['label' => 'About the system', 'route' => 'about-system', 'active' => 'about-system', 'icon' => 'map', 'can' => null],
            ],
        ];

        return collect($sections)
            ->map(fn ($items) => collect($items)
                ->filter(fn ($item) => $item['can'] === null || $user->can($item['can']))
                ->map(function ($item) {
                    $item['active'] = (array) $item['active'];
                    $item['badge'] = isset($item['badge']) ? ($item['badge'])() : null;

                    return $item;
                })
                ->values()
                ->all())
            ->filter()
            ->all();
    }

    private function myDayCount(User $user): ?int
    {
        return Task::pending()->where('assigned_to', $user->id)->where('due_at', '<=', now()->endOfDay())->count() ?: null;
    }
}
