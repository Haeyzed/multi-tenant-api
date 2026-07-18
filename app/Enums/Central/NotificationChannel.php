<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum NotificationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';
    case IN_APP = 'in_app';
    case SLACK = 'slack';
    case WEBHOOK = 'webhook';
    case DISCORD = 'discord';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $channel): array => [
                ...$carry,
                $channel->value => $channel->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::PUSH => 'Push Notification',
            self::IN_APP => 'In-App',
            self::SLACK => 'Slack',
            self::WEBHOOK => 'Webhook',
            self::DISCORD => 'Discord',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EMAIL => 'mail',
            self::SMS => 'message-square',
            self::PUSH => 'bell',
            self::IN_APP => 'inbox',
            self::SLACK => 'slack',
            self::WEBHOOK => 'link',
            self::DISCORD => 'discord',
        };
    }
}
