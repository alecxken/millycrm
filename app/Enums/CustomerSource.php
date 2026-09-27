<?php

namespace App\Enums;

enum CustomerSource: string
{
    use HasOptions;

    case WalkIn = 'walk_in';
    case Website = 'website';
    case Referral = 'referral';
    case Social = 'social';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::Social => 'Social media',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
            self::Corporate => 'Corporate',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::WalkIn => 'amber',
            self::Website => 'sky',
            self::Referral => 'emerald',
            self::Social => 'violet',
            self::Phone => 'slate',
            self::WhatsApp => 'teal',
            self::Corporate => 'indigo',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::WalkIn => 'building-storefront',
            self::Website => 'globe-alt',
            self::Referral => 'hand-thumb-up',
            self::Social => 'hashtag',
            self::Phone => 'phone',
            self::WhatsApp => 'chat-bubble-left-right',
            self::Corporate => 'building-office',
        };
    }
}
