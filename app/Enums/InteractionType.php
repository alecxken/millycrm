<?php

namespace App\Enums;

enum InteractionType: string
{
    use HasOptions;

    case Call = 'call';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';
    case Note = 'note';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Meeting => 'Meeting',
            self::Note => 'Note',
            self::Sms => 'SMS',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Call => 'teal',
            self::Email => 'sky',
            self::WhatsApp => 'emerald',
            self::Meeting => 'violet',
            self::Note => 'slate',
            self::Sms => 'indigo',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Call => 'phone',
            self::Email => 'envelope',
            self::WhatsApp => 'chat-bubble-left-right',
            self::Meeting => 'users',
            self::Note => 'pencil',
            self::Sms => 'device-phone-mobile',
        };
    }
}
