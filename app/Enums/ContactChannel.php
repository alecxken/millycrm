<?php

namespace App\Enums;

enum ContactChannel: string
{
    use HasOptions;

    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Email => 'sky',
            self::Phone => 'slate',
            self::WhatsApp => 'teal',
            self::Sms => 'violet',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::WhatsApp => 'chat-bubble-left-right',
            self::Sms => 'device-phone-mobile',
        };
    }
}
