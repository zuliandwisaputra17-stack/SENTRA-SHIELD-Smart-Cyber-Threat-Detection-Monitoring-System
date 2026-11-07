<?php

namespace App\Enums;

enum ReportGenerator: string
{
    case Llm = 'llm';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Llm => 'LLM lokal',
            self::Template => 'Template',
        };
    }
}
