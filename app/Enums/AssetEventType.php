<?php

namespace App\Enums;

/**
 * Immutable history entries for a serialized asset. Assembly and disassembly
 * are recorded events so warranty/recall lookups can answer "WD-44521 was in
 * SV-8812 from March-June" (PROJECT-BRIEF.md §5).
 */
enum AssetEventType: string
{
    case Created = 'created';
    case Assembled = 'assembled';
    case Disassembled = 'disassembled';
    case Moved = 'moved';
    case Retired = 'retired';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
