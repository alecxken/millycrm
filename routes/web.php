<?php

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\CustomerExportController;
use App\Http\Controllers\QuotePrintController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/dashboard');

// Public, signed feedback form (link sent to travellers after their trip).
Volt::route('feedback/{booking}/share', 'pages.feedback.public')
    ->middleware(['signed', 'throttle:20,1'])
    ->name('feedback.public');

Route::middleware('auth')->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard')->middleware('can:dashboard.view');
    Volt::route('my-day', 'pages.my-day')->name('my-day')->middleware('can:tasks.view');

    // Customer 360
    Route::middleware('can:customers.view')->group(function () {
        Volt::route('customers', 'pages.customers.index')->name('customers.index');
        Volt::route('customers/{customer}', 'pages.customers.show')->name('customers.show');
        Route::get('customers/{customer}/export', CustomerExportController::class)->name('customers.export')->middleware('can:customers.privacy');
    });

    // Sales force automation
    Route::middleware('can:pipeline.view')->group(function () {
        Volt::route('pipeline', 'pages.pipeline.index')->name('pipeline');
        Volt::route('quotes/{quote}', 'pages.quotes.builder')->name('quotes.edit');
        Route::get('quotes/{quote}/print', QuotePrintController::class)->name('quotes.print');
        Volt::route('bookings', 'pages.bookings.index')->name('bookings.index');
        Volt::route('bookings/{booking}', 'pages.bookings.show')->name('bookings.show');
    });

    // Service & support
    Route::middleware('can:tickets.view')->group(function () {
        Volt::route('tickets', 'pages.tickets.index')->name('tickets.index');
        Volt::route('tickets/{ticket}', 'pages.tickets.show')->name('tickets.show');
    });
    Volt::route('feedback', 'pages.feedback.index')->name('feedback.index')->middleware('can:feedback.view');

    // Marketing
    Route::middleware('can:marketing.view')->prefix('marketing')->group(function () {
        Volt::route('segments', 'pages.marketing.segments')->name('segments.index');
        Volt::route('campaigns', 'pages.marketing.campaigns')->name('campaigns.index');
        Volt::route('campaigns/{campaign}', 'pages.marketing.campaign')->name('campaigns.show');
    });

    Volt::route('suppliers', 'pages.suppliers.index')->name('suppliers.index')->middleware('can:suppliers.view');

    // MIS reports
    Route::middleware('can:reports.view')->prefix('reports')->group(function () {
        Volt::route('/', 'pages.reports.builder')->name('reports.builder');
        Route::get('export', ReportExportController::class)->name('reports.export');
        Volt::route('scheduled', 'pages.reports.scheduled')->name('reports.scheduled');
    });

    // Governance
    Route::prefix('admin')->group(function () {
        Volt::route('appearance', 'pages.admin.appearance')->name('admin.appearance')->middleware('can:settings.manage');
        Volt::route('users', 'pages.admin.users')->name('admin.users')->middleware('can:admin.users');
        Volt::route('audit', 'pages.admin.audit')->name('admin.audit')->middleware('can:admin.audit');
        Volt::route('backups', 'pages.admin.backups')->name('admin.backups')->middleware('can:admin.backup');
        Route::get('backups/{name}', BackupDownloadController::class)->name('admin.backups.download')->middleware('can:admin.backup');
    });

    Volt::route('about-system', 'pages.about-system')->name('about-system');
    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
