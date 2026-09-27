<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Negotiating = 'negotiating';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Quoted => 'Quoted',
            self::Negotiating => 'Negotiating',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /** Colour key understood by <x-badge>. */
    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::Contacted => 'indigo',
            self::Quoted => 'violet',
            self::Negotiating => 'amber',
            self::Won => 'emerald',
            self::Lost => 'rose',
        };
    }

    /** Heroicon name (outline). */
    public function icon(): string
    {
        return match ($this) {
            self::New => 'inbox',
            self::Contacted => 'chat-bubble-left',
            self::Quoted => 'document-text',
            self::Negotiating => 'scale',
            self::Won => 'trophy',
            self::Lost => 'x-circle',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /** Default win probability (%) used for weighted pipeline value. */
    public function probability(): int
    {
        return match ($this) {
            self::New => 10,
            self::Contacted => 25,
            self::Quoted => 50,
            self::Negotiating => 75,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    /** @return array<int, self> the Kanban columns in order */
    public static function board(): array
    {
        return self::cases();
    }
}
