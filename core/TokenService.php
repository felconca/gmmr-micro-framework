<?php

/**
 * TokenService
 *
 * Pure PHP JWT — no Composer, no dependencies.
 * Implements HS256 signing.
 *
 * Generates two tokens:
 *   access  token  – short-lived  (default 15 min)  → stored in HttpOnly cookie
 *   refresh token  – long-lived   (default 7 days)  → stored in HttpOnly cookie
 *
 * Config keys read from config.php:
 *   jwt.secret           – signing secret (required, min 32 chars recommended)
 *   jwt.access_ttl       – access token TTL in seconds  (default 900 = 15 min)
 *   jwt.refresh_ttl      – refresh token TTL in seconds (default 604800 = 7 days)
 *   jwt.cookie_prefix    – prefix for cookie names      (default 'app')
 *   jwt.secure           – set Secure flag on cookies   (default true)
 *   jwt.same_site        – SameSite policy              (default 'Strict')
 */
class TokenService
{
    private string $secret;
    private int    $accessTtl;
    private int    $refreshTtl;
    private string $cookiePrefix;
    private bool   $secure;
    private string $sameSite;

    public function __construct(array $jwtConfig)
    {
        $this->secret       = $jwtConfig['secret']        ?? '';
        $this->accessTtl    = (int)($jwtConfig['access_ttl']  ?? 900);
        $this->refreshTtl   = (int)($jwtConfig['refresh_ttl'] ?? 604800);
        $this->cookiePrefix = $jwtConfig['cookie_prefix'] ?? 'app';
        $this->secure       = (bool)($jwtConfig['secure']     ?? true);
        $this->sameSite     = $jwtConfig['same_site']     ?? 'Strict';

        if (empty($this->secret)) {
            throw new RuntimeException('JWT secret is not set in config.php under jwt.secret');
        }
    }

    // ── Token generation ──────────────────────────────────────────────────────

    /**
     * Generate both tokens and write them to HttpOnly cookies.
     * Returns the token pair as an array (useful for returning in the response body too).
     *
     * $payload = any user data to embed, e.g. ['id' => 1, 'role' => 'admin']
     */
    public function issue(array $payload): array
    {
        $now = time();

        $accessPayload = array_merge($payload, [
            'type' => 'access',
            'iat'  => $now,
            'exp'  => $now + $this->accessTtl,
        ]);

        $refreshPayload = [
            'sub'  => $payload['id'] ?? $payload['sub'] ?? null,
            'type' => 'refresh',
            'iat'  => $now,
            'exp'  => $now + $this->refreshTtl,
        ];

        $accessToken  = $this->encode($accessPayload);
        $refreshToken = $this->encode($refreshPayload);

        $this->setCookie('access_token',  $accessToken,  $this->accessTtl);
        $this->setCookie('refresh_token', $refreshToken, $this->refreshTtl);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $this->accessTtl,
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * Rotate tokens — verify the refresh token then issue a fresh pair.
     * Call this on your /refresh endpoint.
     */
    public function refresh(): array
    {
        $token   = $this->getRefreshTokenFromCookie();
        $payload = $this->verify($token, 'refresh');

        // Strip JWT-specific claims, keep user data for new access token
        $userPayload = array_diff_key($payload, array_flip(['type', 'iat', 'exp', 'sub']));
        $userPayload['id'] = $payload['sub'] ?? null;

        return $this->issue($userPayload);
    }

    /**
     * Clear both token cookies (logout).
     */
    public function revoke(): void
    {
        $this->clearCookie('access_token');
        $this->clearCookie('refresh_token');
    }

    // ── Token verification ────────────────────────────────────────────────────

    /**
     * Verify and decode a JWT string.
     * Throws RuntimeException on any failure.
     *
     * $expectedType = 'access' | 'refresh' | null (skip type check)
     */
    public function verify(string $token, ?string $expectedType = 'access'): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$headerB64, $payloadB64, $sig] = $parts;

        // Verify signature
        $expected = $this->sign("{$headerB64}.{$payloadB64}");
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('Token signature is invalid.');
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Token payload is malformed.');
        }

        // Check expiry
        if (isset($payload['exp']) && time() > $payload['exp']) {
            throw new RuntimeException('Token has expired.');
        }

        // Check type
        if ($expectedType !== null && ($payload['type'] ?? '') !== $expectedType) {
            throw new RuntimeException("Expected [{$expectedType}] token, got [{$payload['type']}].");
        }

        return $payload;
    }

    /**
     * Read and verify the access token from the cookie.
     * Returns the decoded payload.
     */
    // public function verifyFromCookie(): array
    // {
    //     $cookieName = "{$this->cookiePrefix}_access_token";
    //     $token      = $_COOKIE[$cookieName] ?? '';

    //     if (empty($token)) {
    //         throw new RuntimeException('Access token cookie is missing.');
    //     }

    //     return $this->verify($token, 'access');
    // }
    public function verifyFromCookie(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->verify($token, 'access');
        }

        // Fallback to cookie (browser without Authorization header)
        $cookieName = "{$this->cookiePrefix}_access_token";
        $token      = $_COOKIE[$cookieName] ?? '';

        if (empty($token)) {
            throw new RuntimeException('Access token is missing.');
        }

        return $this->verify($token, 'access');
    }
    // ── Cookie helpers ────────────────────────────────────────────────────────

    private function setCookie(string $name, string $value, int $ttl): void
    {
        setcookie(
            "{$this->cookiePrefix}_{$name}",
            $value,
            [
                'expires'  => time() + $ttl,
                'path'     => '/',
                'httponly' => true,                 // not accessible via JS
                'secure'   => $this->secure,        // HTTPS only (set false for local dev)
                'samesite' => $this->sameSite,      // CSRF protection
            ]
        );
    }

    private function clearCookie(string $name): void
    {
        setcookie(
            "{$this->cookiePrefix}_{$name}",
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $this->secure,
                'samesite' => $this->sameSite,
            ]
        );
    }

    private function getRefreshTokenFromCookie(): string
    {
        $cookieName = "{$this->cookiePrefix}_refresh_token";
        $token      = $_COOKIE[$cookieName] ?? '';

        if (empty($token)) {
            throw new RuntimeException('Refresh token cookie is missing.');
        }

        return $token;
    }

    // ── JWT encoding internals ────────────────────────────────────────────────

    private function encode(array $payload): string
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $sig     = $this->sign("{$header}.{$payload}");

        return "{$header}.{$payload}.{$sig}";
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secret, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
