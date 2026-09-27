<?php

namespace App\Enums;

enum TicketCategory: string
{
    use HasOptions;

    case Complaint = 'complaint';
    case ChangeRequest = 'change_request';
    case Refund = 'refund';
    case LostDocument = 'lost_document';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Complaint => 'Complaint',
            self::ChangeRequest => 'Change request',
            self::Refund => 'Refund',
            self::LostDocument => 'Lost document',
            self::General => 'General',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Complaint => 'rose',
            self::ChangeRequest => 'sky',
            self::Refund => 'amber',
            self::LostDocument => 'violet',
            self::General => 'slate',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::Complaint => 'face-frown',
            self::ChangeRequest => 'arrows-right-left',
            self::Refund => 'arrow-uturn-left',
            self::LostDocument => 'document-magnifying-glass',
            self::General => 'question-mark-circle',
        };
    }
}
