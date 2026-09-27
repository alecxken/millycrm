{{-- SLA countdown badge: colour + icon + words. --}}
@php
    $due = $ticket->sla_due_at;
    if (! $ticket->isOpen()) {
        [$color, $icon, $label] = $ticket->isBreached() ? ['amber', 'check-circle', 'Resolved late'] : ['emerald', 'check-circle', 'Within SLA'];
    } elseif ($due === null) {
        [$color, $icon, $label] = ['slate', 'clock', 'No SLA'];
    } elseif ($due->isPast()) {
        [$color, $icon, $label] = ['rose', 'exclamation-triangle', 'Breached '.$due->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE).' ago'];
    } elseif ($due->lte(now()->addHours(4))) {
        [$color, $icon, $label] = ['amber', 'clock', $due->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE).' left'];
    } else {
        [$color, $icon, $label] = ['teal', 'clock', $due->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE).' left'];
    }
@endphp
<x-badge :color="$color" :icon="$icon" :label="$label" title="SLA due {{ fdate($due, true) }}" />
