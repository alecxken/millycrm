<?php

use App\Enums\Currency;
use App\Enums\EnquiryStatus;
use App\Enums\LifecycleStage;
use App\Services\QuoteService;
use Carbon\Carbon;

it('formats money in KES by default, with compact amounts', function () {
    expect(money(1234567))->toBe('KES 1,234,567')
        ->and(money(1234567, compact: true))->toBe('KES 1.2M')
        ->and(money(950, Currency::USD))->toBe('US$ 950.00');
});

it('formats dates like "26 Sep 2026"', function () {
    expect(fdate(Carbon::create(2026, 9, 26, 14, 5)))->toBe('26 Sep 2026')
        ->and(fdate(Carbon::create(2026, 9, 26, 14, 5), true))->toBe('26 Sep 2026, 14:05')
        ->and(fdate(null))->toBe('—');
});

it('applies markup to cost', function () {
    expect(QuoteService::priceFor(100000, 15))->toBe(115000.0)
        ->and(QuoteService::priceFor(1000, 12.5))->toBe(1125.0);
});

it('orders lifecycle stages so promotion never demotes', function () {
    expect(LifecycleStage::Vip->rank())->toBeGreaterThan(LifecycleStage::Repeat->rank())
        ->and(LifecycleStage::Lead->rank())->toBeGreaterThan(LifecycleStage::Inactive->rank());
});

it('gives every enquiry stage a win probability', function () {
    expect(EnquiryStatus::Won->probability())->toBe(100)
        ->and(EnquiryStatus::Lost->probability())->toBe(0)
        ->and(EnquiryStatus::Lost->isOpen())->toBeFalse();
});
