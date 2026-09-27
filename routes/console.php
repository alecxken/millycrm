<?php

use Illuminate\Support\Facades\Schedule;

// MIS scheduled reports: checks hourly, each report runs on its own frequency.
Schedule::command('crm:run-scheduled-reports')->hourly();

// Follow-up automation and lifecycle rules.
Schedule::command('crm:daily')->dailyAt('06:00');

// Nightly backup (Murray: backup & recovery).
Schedule::command('crm:backup')->dailyAt('02:00');
