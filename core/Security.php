<?php
/**
 * Panelion - Security Utilities
 */

namespace Panelion\Core;

class Security
{
    /**
     * Hash a password using bcrypt
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken(Session $session): string
    {
        $token = bin2hex(random_bytes(32));
        $session->set('csrf_token', $token);
        $session->set('csrf_token_time', time());
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken(string $token, Session $session): bool
    {
        $storedToken = $session->get('csrf_token');
        $tokenTime = $session->get('csrf_token_time', 0);

        if (empty($token) || empty($storedToken)) {
            return false;
        }

        // Check token expiry (1 hour)
        if (time() - $tokenTime > 3600) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * Sanitize input string
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        return $filename;
    }

    /**
     * Validate an IP address
     */
    public static function validateIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate a domain name
     */
    public static function validateDomain(string $domain): bool
    {
        return (bool) preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain);
    }

    /**
     * Validate email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a port number
     */
    public static function validatePort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    /**
     * Validate username (alphanumeric + underscore, 3-32 chars)
     */
    public static function validateUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,31}$/', $username);
    }

    /**
     * Check password strength
     */
    public static function checkPasswordStrength(string $password, int $minLength = 12): array
    {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }

    /**
     * Generate a random API key
     */
    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a random password
     */
    public static function generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit(Database $db, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND created_at > ?",
            [$identifier, $cutoff]
        );
        return $count < $maxAttempts;
    }

    /**
     * Record a rate limit hit
     */
    public static function recordRateLimitHit(Database $db, string $identifier): void
    {
        $db->insert('rate_limits', [
            'identifier' => $identifier,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get client IP
     */
    public static function getClientIP(): string
    {
        // Only trust REMOTE_ADDR in production; forwarded headers can be spoofed
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Generate TOTP secret for 2FA
     */
    public static function generateTOTPSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify TOTP code
     */
    public static function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $timeSlice = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::calculateTOTP($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    private static function calculateTOTP(string $secret, int $timeSlice): string
    {
        // Base32 decode
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($secret) as $char) {
            $binary .= str_pad(decbin(strpos($chars, strtoupper($char))), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($binary, 8) as $byte) {
            $key .= chr(bindec($byte));
        }

        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $otp = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }
}
