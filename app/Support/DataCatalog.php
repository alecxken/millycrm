<?php

namespace App\Support;

use App\Models;
use Illuminate\Support\Facades\Schema;

/**
 * Describes the CRM's data sources and data types, generated from the
 * live models and database schema (used on /about-system).
 */
class DataCatalog
{
    /** model => [source, origin (internal/external), channel, description] */
    private const SOURCES = [
        Models\Customer::class => ['Customer profiles', 'Internal + customer-provided', 'Walk-in forms, website, WhatsApp, phone', 'Identity, contact, travel documents, consent'],
        Models\CustomerPreference::class => ['Travel preferences', 'Customer-provided', 'Consultant conversations', 'Seat, meals, budget, style, favourite airlines & destinations'],
        Models\Contact::class => ['Account contacts', 'Customer-provided', 'Corporate & group coordinators', 'People who book on behalf of companies and groups'],
        Models\Enquiry::class => ['Enquiries (leads)', 'Internal', 'All sales channels', 'Trip requests, budget, stage, win probability'],
        Models\Quote::class => ['Quotes', 'Internal', 'Quote builder', 'Priced proposals, validity, status'],
        Models\QuoteItem::class => ['Quote line items', 'External (supplier rates)', 'Airlines, hotels, tour operators', 'Supplier cost, markup, price'],
        Models\Booking::class => ['Bookings', 'Internal', 'Converted quotes', 'Confirmed trips, dates, value, status'],
        Models\Payment::class => ['Payments', 'External (M-Pesa, banks, cards)', 'Payment confirmations', 'Amounts, methods, transaction references'],
        Models\Supplier::class => ['Suppliers', 'External', 'Supplier contracts', 'Commission rates, ratings, contacts'],
        Models\Interaction::class => ['Interactions', 'Internal + customer', 'Calls, emails, WhatsApp, meetings', 'Conversation history and notes'],
        Models\Task::class => ['Tasks & follow-ups', 'Internal (partly automated)', 'Consultants and system rules', 'What needs doing, by whom, by when'],
        Models\ServiceTicket::class => ['Service tickets', 'Customer-reported', 'Phone, WhatsApp, email', 'Complaints, changes, refunds, lost documents'],
        Models\Feedback::class => ['Feedback & NPS', 'Customer-provided', 'Signed post-trip survey link', 'Scores, ratings and free-text comments'],
        Models\Segment::class => ['Segments', 'Internal (derived)', 'Segment builder', 'Rule definitions for target audiences'],
        Models\Campaign::class => ['Campaigns', 'Internal', 'Marketing', 'Messages, audiences, opens, attributed bookings'],
        Models\ScheduledReport::class => ['Scheduled reports', 'Internal (derived)', 'Report scheduler', 'Report definitions and last outputs'],
    ];

    private const UNSTRUCTURED = ['notes', 'body', 'comment', 'description', 'resolution', 'special_needs', 'last_output'];

    public function entries(): array
    {
        return collect(self::SOURCES)->map(function ($meta, $class) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $class;
            $table = $model->getTable();
            $columns = Schema::getColumns($table);
            $names = array_column($columns, 'name');

            $json = collect($columns)->filter(fn ($c) => in_array(strtolower($c['type_name']), ['json', 'jsonb'], true) || in_array($c['name'], array_keys(array_filter($model->getCasts(), fn ($cast) => $cast === 'array'))))->pluck('name')->values()->all();
            $text = array_values(array_intersect($names, self::UNSTRUCTURED));

            $types = ['Structured'];
            if ($json) {
                $types[] = 'Semi-structured';
            }
            if ($text) {
                $types[] = 'Unstructured';
            }

            return [
                'entity' => $meta[0],
                'model' => class_basename($class),
                'table' => $table,
                'origin' => $meta[1],
                'channel' => $meta[2],
                'description' => $meta[3],
                'types' => $types,
                'json_fields' => $json,
                'text_fields' => $text,
                'fields' => count($names),
                'rows' => $class::query()->withoutGlobalScopes()->count(),
                'sensitive' => array_values(array_intersect($names, ['passport_number', 'date_of_birth', 'email', 'phone'])),
            ];
        })->values()->all();
    }
}
