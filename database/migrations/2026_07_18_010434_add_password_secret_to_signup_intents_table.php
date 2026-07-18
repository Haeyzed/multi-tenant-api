<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signup_intents', function (Blueprint $table): void {
            $table->text('password_secret')->nullable()->after('payload');
        });

        DB::table('signup_intents')
            ->orderBy('id')
            ->chunk(100, function ($intents): void {
                foreach ($intents as $intent) {
                    $payload = $this->decodePayload($intent->payload);
                    $password = $payload['password'] ?? null;

                    if (! is_string($password) || $password === '') {
                        continue;
                    }

                    unset($payload['password'], $payload['password_confirmation']);

                    DB::table('signup_intents')
                        ->where('id', $intent->id)
                        ->update([
                            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                            'password_secret' => Crypt::encryptString($password),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('signup_intents')
            ->whereNotNull('password_secret')
            ->orderBy('id')
            ->chunk(100, function ($intents): void {
                foreach ($intents as $intent) {
                    try {
                        $password = Crypt::decryptString((string) $intent->password_secret);
                    } catch (\Throwable) {
                        continue;
                    }

                    $payload = $this->decodePayload($intent->payload);
                    $payload['password'] = $password;

                    DB::table('signup_intents')
                        ->where('id', $intent->id)
                        ->update([
                            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                        ]);
                }
            });

        Schema::table('signup_intents', function (Blueprint $table): void {
            $table->dropColumn('password_secret');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
};
