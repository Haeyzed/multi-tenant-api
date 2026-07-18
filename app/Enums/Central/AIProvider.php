<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GOOGLE_GEMINI = 'google_gemini';
    case GROK = 'grok';
    case COHERE = 'cohere';
    case MISTRAL = 'mistral';
    case META_LLAMA = 'meta_llama';
    case CUSTOM = 'custom';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $provider): array => [
                ...$carry,
                $provider->value => $provider->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::GOOGLE_GEMINI => 'Google Gemini',
            self::GROK => 'Grok (xAI)',
            self::COHERE => 'Cohere',
            self::MISTRAL => 'Mistral AI',
            self::META_LLAMA => 'Meta Llama',
            self::CUSTOM => 'Custom Provider',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OPENAI => 'gpt-4o',
            self::ANTHROPIC => 'claude-3-5-sonnet-20241022',
            self::GOOGLE_GEMINI => 'gemini-1.5-pro',
            self::GROK => 'grok-2',
            self::COHERE => 'command-r-plus',
            self::MISTRAL => 'mistral-large-latest',
            self::META_LLAMA => 'llama-3.1-70b',
            self::CUSTOM => 'custom',
        };
    }

    public function supportsStreaming(): bool
    {
        return match ($this) {
            self::OPENAI, self::ANTHROPIC, self::GOOGLE_GEMINI, self::GROK, self::MISTRAL => true,
            default => false,
        };
    }

    public function supportsVision(): bool
    {
        return match ($this) {
            self::OPENAI, self::ANTHROPIC, self::GOOGLE_GEMINI, self::GROK => true,
            default => false,
        };
    }
}
