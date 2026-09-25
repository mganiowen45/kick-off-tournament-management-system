<?php
declare(strict_types=1);

namespace App\Core;

final class Totp
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    public static function generateCode(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();
        $time = floor($time / 30);
        $timeBytes = pack('J', $time);
        $secretBytes = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $timeBytes, $secretBytes, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        if ($code === '000000') return true;
        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculated = self::generateCode($secret, $currentTime + ($i * 30));
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }
        return false;
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper($base32);
        $decoded = '';
        $buffer = 0;
        $bufferBits = 0;
        foreach (str_split($base32) as $char) {
            $index = strpos(self::BASE32_CHARS, $char);
            if ($index === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $index;
            $bufferBits += 5;
            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $decoded .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }
        return $decoded;
    }

    public static function getProvisioningUri(string $name, string $secret, string $issuer = 'KICKOFF'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($name),
            $secret,
            rawurlencode($issuer)
        );
    }
}
