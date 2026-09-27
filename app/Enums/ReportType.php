<?php

namespace App\Enums;

enum ReportType: string
{
    use HasOptions;

    case SalesSummary = 'sales_summary';
    case Pipeline = 'pipeline';
    case ServiceLevels = 'service_levels';
    case CustomerGrowth = 'customer_growth';
    case AdHoc = 'ad_hoc';

    public function label(): string
    {
        return match ($this) {
            self::SalesSummary => 'Sales summary',
            self::Pipeline => 'Pipeline status',
            self::ServiceLevels => 'Service levels (SLA)',
            self::CustomerGrowth => 'Customer growth',
            self::AdHoc => 'Saved ad-hoc report',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::SalesSummary => 'teal',
            self::Pipeline => 'violet',
            self::ServiceLevels => 'amber',
            self::CustomerGrowth => 'sky',
            self::AdHoc => 'slate',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::SalesSummary => 'chart-bar',
            self::Pipeline => 'funnel',
            self::ServiceLevels => 'lifebuoy',
            self::CustomerGrowth => 'user-plus',
            self::AdHoc => 'table-cells',
        };
    }
}
