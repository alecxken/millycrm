<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Single source of truth for RBAC. The seeder syncs these into spatie tables,
 * the sidebar hides modules a role can't use, and policies check them.
 */
final class Permissions
{
    public const ALL = [
        'dashboard.view' => 'View dashboard',
        'customers.view' => 'View customers',
        'customers.manage' => 'Create and edit customers',
        'customers.privacy' => 'Export / anonymise customer data',
        'pipeline.view' => 'View sales pipeline',
        'pipeline.manage' => 'Manage enquiries, quotes and bookings',
        'pipeline.view_all' => "See every consultant's enquiries",
        'discounts.approve' => 'Approve quote discounts',
        'tasks.view' => 'Use My Day and tasks',
        'tickets.view' => 'View service tickets',
        'tickets.manage' => 'Work service tickets',
        'feedback.view' => 'View customer feedback',
        'marketing.view' => 'View segments and campaigns',
        'marketing.manage' => 'Build segments and send campaigns',
        'suppliers.view' => 'View suppliers',
        'suppliers.manage' => 'Manage suppliers',
        'reports.view' => 'Run ad-hoc and scheduled reports',
        'admin.audit' => 'View the audit trail',
        'admin.users' => 'Manage staff accounts',
        'admin.backup' => 'Run and download backups',
    ];

    /** @return array<string, array<int, string>> */
    public static function matrix(): array
    {
        $all = array_keys(self::ALL);

        return [
            Role::Owner->value => $all,
            Role::Manager->value => array_values(array_diff($all, ['admin.backup'])),
            Role::Consultant->value => [
                'dashboard.view', 'customers.view', 'customers.manage', 'pipeline.view', 'pipeline.manage',
                'tasks.view', 'suppliers.view',
            ],
            Role::Marketing->value => [
                'dashboard.view', 'customers.view', 'marketing.view', 'marketing.manage', 'feedback.view', 'reports.view',
            ],
            Role::Support->value => [
                'dashboard.view', 'customers.view', 'tasks.view', 'tickets.view', 'tickets.manage', 'feedback.view', 'suppliers.view',
            ],
        ];
    }
}
