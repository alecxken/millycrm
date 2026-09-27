<?php

namespace App\Enums;

enum CampaignChannel: string
{
    use HasOptions;

    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Email => 'sky',
            self::Sms => 'violet',
            self::WhatsApp => 'emerald',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Email => 'envelope',
            self::Sms => 'device-phone-mobile',
            self::WhatsApp => 'chat-bubble-left-right',
        };
    }
}
