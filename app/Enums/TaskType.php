<?php

namespace App\Enums;

enum TaskType: string
{
    use HasOptions;

    case FollowUp = 'follow_up';
    case FeedbackRequest = 'feedback_request';
    case Rebook = 'rebook';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::FollowUp => 'Follow-up',
            self::FeedbackRequest => 'Feedback request',
            self::Rebook => 'Re-booking suggestion',
            self::General => 'General',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::FollowUp => 'teal',
            self::FeedbackRequest => 'violet',
            self::Rebook => 'amber',
            self::General => 'slate',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::FollowUp => 'phone-arrow-up-right',
            self::FeedbackRequest => 'chat-bubble-bottom-center-text',
            self::Rebook => 'arrow-path',
            self::General => 'clipboard-document-check',
        };
    }
}
