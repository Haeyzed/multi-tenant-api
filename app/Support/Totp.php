<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class Totp
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    private const WINDOW = 1;

    /**
     * Generate a Base32-encoded secret suitable for authenticator apps.
     */
    public static function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Build an otpauth:// URI for QR code provisioning.
     */
    public static function provisioningUri(string $secret, string $email, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Verify a user-provided TOTP code against the secret.
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = trim($code);

        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp ??= time();
        $timeSlice = (int)floor($timestamp / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::at($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function at(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        $modulo = 10 ** self::DIGITS;

        return str_pad((string)($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
            $value = strpos($alphabet, $secret[$i]);

            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }

    /**
     * Generate the current TOTP code for a secret.
     */
    public static function currentCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $timeSlice = (int)floor($timestamp / self::PERIOD);

        return self::at($secret, $timeSlice);
    }

    /**
     * Generate hashed recovery codes.
     *
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $code = Str::upper(Str::random(4) . '-' . Str::random(4));
            $plain[] = $code;
            $hashed[] = bcrypt($code);
        }

        return compact('plain', 'hashed');
    }

    /**
     * Consume a recovery code if it matches one of the hashed codes.
     *
     * @param list<string> $hashedCodes
     * @return list<string>|null
     */
    public static function consumeRecoveryCode(array $hashedCodes, string $code): ?array
    {
        foreach ($hashedCodes as $index => $hashed) {
            if (password_verify($code, $hashed)) {
                unset($hashedCodes[$index]);

                return array_values($hashedCodes);
            }
        }

        return null;
    }
}
