<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum StorageProvider: string
{
    case LOCAL = 'local';
    case AWS_S3 = 'aws_s3';
    case CLOUDINARY = 'cloudinary';
    case GOOGLE_CLOUD = 'google_cloud';
    case AZURE_BLOB = 'azure_blob';
    case DIGITAL_OCEAN = 'digital_ocean';
    case WASABI = 'wasabi';
    case MINIO = 'minio';
    case BACKBLAZE = 'backblaze';

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
            self::LOCAL => 'Local Storage',
            self::AWS_S3 => 'AWS S3',
            self::CLOUDINARY => 'Cloudinary',
            self::GOOGLE_CLOUD => 'Google Cloud Storage',
            self::AZURE_BLOB => 'Azure Blob Storage',
            self::DIGITAL_OCEAN => 'DigitalOcean Spaces',
            self::WASABI => 'Wasabi',
            self::MINIO => 'MinIO',
            self::BACKBLAZE => 'Backblaze B2',
        };
    }

    public function supportsCdn(): bool
    {
        return match ($this) {
            self::LOCAL => false,
            default => true,
        };
    }
}
