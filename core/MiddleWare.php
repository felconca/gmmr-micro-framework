<?php

/**
 * Middleware
 *
 * A collection of static guards that can be called at the top of any
 * controller method to protect it before the main logic runs.
 *
 * Usage inside a controller:
 *
 *   Middleware::auth($request, $response);          // must have valid access token
 *   Middleware::guest($request, $response);         // must NOT be logged in
 *   Middleware::role(['admin'], $request, $response); // token role must match
 *   Middleware::method('POST', $request, $response); // HTTP method guard
 *
 * All guards stop execution immediately (via $response->send) if they fail.
 */
class Middleware
{
    // ── Auth guard ────────────────────────────────────────────────────────────

    /**
     * Require a valid access token cookie.
     * Returns the decoded payload so you can use it right away.
     *
     *   $user = Middleware::auth($request, $response);
     *   echo $user['id'];
     */
    public static function auth(Request $request, Response $response): array
    {
        try {
            $tokens  = self::tokenService();
            $payload = $tokens->verifyFromCookie();
            return $payload;
        } catch (RuntimeException $e) {
            $response->send(['status' => 401, 'error' => 'Unauthorized: ' . $e->getMessage()], 401);
        }
    }

    /**
     * Block authenticated users (e.g. login / register pages).
     * If a valid token is present, returns 403.
     */
    public static function guest(Request $request, Response $response): void
    {
        try {
            self::tokenService()->verifyFromCookie();
            // If we get here the token is valid — block the request
            $response->send(['status' => 403, 'error' => 'Already authenticated'], 403);
        } catch (RuntimeException) {
            // No valid token = guest confirmed, let through
        }
    }

    /**
     * Require the token payload to contain a 'role' field matching one of $allowed.
     * Always runs auth() first.
     *
     *   $user = Middleware::role(['admin', 'superadmin'], $request, $response);
     */
    public static function role(array $allowed, Request $request, Response $response): array
    {
        $payload = self::auth($request, $response);

        $role = $payload['role'] ?? null;
        if (!in_array($role, $allowed, true)) {
            $response->send([
                'status' => 403,
                'error'  => "Forbidden. Required role: " . implode(' or ', $allowed),
            ], 403);
        }

        return $payload;
    }

    /**
     * Require specific HTTP method(s).
     *
     *   Middleware::method('POST', $request, $response);
     *   Middleware::method(['GET', 'HEAD'], $request, $response);
     */
    public static function method(string|array $methods, Request $request, Response $response): void
    {
        $allowed = array_map('strtoupper', (array) $methods);
        if (!in_array(strtoupper($request->method()), $allowed, true)) {
            $response->send([
                'status' => 405,
                // 'error'  => 'Method Not Allowed. Expected: ' . implode(', ', $allowed),
                'error'  => 'Method Not Allowed'
            ], 405);
        }
    }

    // ── Token service factory ─────────────────────────────────────────────────

    /**
     * Build a TokenService from config.php.
     * Cached so the file is only read once per request.
     */
    private static ?TokenService $tokenService = null;

    private static function tokenService(): TokenService
    {
        if (self::$tokenService === null) {
            $configPath = __DIR__ . '/../config/config.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException('config.php not found.');
            }
            $config = require $configPath;
            self::$tokenService = new TokenService($config['jwt'] ?? []);
        }
        return self::$tokenService;
    }
}
