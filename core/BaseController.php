<?php

/**
 * BaseController
 *
 * Reads all settings from config.php.
 * Supports any database driver: mysqli, pgsql, sqlsrv, sqlite.
 * Connections are lazy and pooled — opened once, reused within the request.
 */
abstract class BaseController
{
    private static array $config      = [];
    private static array $connections = [];   // pool: key → connection instance

    // ── Boot (called by Router before every action) ───────────────────────────
    public function boot(): void
    {
        self::loadConfig();
        date_default_timezone_set(self::config('timezone', 'UTC'));
    }

    // ── DB access ─────────────────────────────────────────────────────────────

    /**
     * Get a database connection by config key.
     * Returns the native connection object for the driver:
     *   mysqli  → mysqli instance
     *   pgsql   → PgSql\Connection (pg_connect)
     *   sqlsrv  → sqlsrv resource
     *   sqlite  → SQLite3 instance
     *
     * Usage:
     *   $this->db()            → default connection (db_default key)
     *   $this->db('secondary') → named connection from config
     *   $this->db($request->input('refdb'))  → dynamic from request
     */
    protected function db(string $key = ''): mixed
    {
        if ($key === '') {
            $key = self::config('db_default', 'primary');
        }

        if (isset(self::$connections[$key])) {
            return self::$connections[$key];
        }

        $dbs = self::config('databases', []);
        if (!isset($dbs[$key])) {
            throw new RuntimeException("Database [{$key}] is not defined in config.php.");
        }

        $c      = $dbs[$key];
        $driver = strtolower($c['driver'] ?? 'mysqli');

        $conn = match ($driver) {
            'mysqli'  => $this->connectMysqli($c),
            'pgsql'   => $this->connectPgsql($c),
            'sqlsrv'  => $this->connectSqlsrv($c),
            'sqlite'  => $this->connectSqlite($c),
            default   => throw new RuntimeException("Unsupported driver [{$driver}] for connection [{$key}]."),
        };

        self::$connections[$key] = $conn;
        return $conn;
    }

    // ── Config helper ─────────────────────────────────────────────────────────

    /**
     * Read any value from config.php.
     * Supports one level of dot notation:  'app.debug',  'cors.allowed_origins'
     *
     *   self::config('timezone')
     *   self::config('app.debug', false)
     */
    protected static function config(string $key, mixed $default = null): mixed
    {
        self::loadConfig();

        if (str_contains($key, '.')) {
            [$section, $subkey] = explode('.', $key, 2);
            return self::$config[$section][$subkey] ?? $default;
        }

        return self::$config[$key] ?? $default;
    }

    // ── HTTP method guard ─────────────────────────────────────────────────────

    protected function mustBe(string $method, Request $request, Response $response): void
    {
        if (strtoupper($request->method()) !== strtoupper($method)) {
            $response->send(['status' => 405, 'error' => 'Method Not Allowed'], 405);
        }
    }

    protected function tokens(): TokenService
    {
        return new TokenService(self::config('jwt', []));
    }

    // ── Driver connectors ─────────────────────────────────────────────────────

    private function connectMysqli(array $c): mysqli
    {
        $conn = new mysqli(
            $c['host']     ?? 'localhost',
            $c['user']     ?? '',
            $c['password'] ?? '',
            $c['name']     ?? '',
            $c['port']     ?? 3306,
        );

        if ($conn->connect_error) {
            throw new RuntimeException('mysqli connect error: ' . $conn->connect_error);
        }

        $conn->set_charset($c['charset'] ?? 'utf8mb4');
        return $conn;
    }

    private function connectPgsql(array $c): mixed
    {
        if (!function_exists('pg_connect')) {
            throw new RuntimeException('pgsql extension is not loaded.');
        }

        $dsn  = sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s",
            $c['host']     ?? 'localhost',
            $c['port']     ?? 5432,
            $c['name']     ?? '',
            $c['user']     ?? '',
            $c['password'] ?? '',
        );

        if (!empty($c['charset'])) {
            $dsn .= " options='--client_encoding={$c['charset']}'";
        }

        $conn = pg_connect($dsn);
        if (!$conn) {
            throw new RuntimeException('pgsql connect failed. Check host, name, user, and password in config.php.');
        }

        return $conn;
    }

    private function connectSqlsrv(array $c): mixed
    {
        if (!function_exists('sqlsrv_connect')) {
            throw new RuntimeException('sqlsrv extension is not loaded.');
        }

        $serverName = ($c['host'] ?? 'localhost') . ',' . ($c['port'] ?? 1433);
        $info = [
            'Database'              => $c['name']     ?? '',
            'UID'                   => $c['user']     ?? '',
            'PWD'                   => $c['password'] ?? '',
            'CharacterSet'          => $c['charset']  ?? 'UTF-8',
            'TrustServerCertificate' => $c['trust_cert'] ?? true,
        ];

        $conn = sqlsrv_connect($serverName, $info);
        if (!$conn) {
            $errors = sqlsrv_errors();
            throw new RuntimeException('sqlsrv connect failed: ' . json_encode($errors));
        }

        return $conn;
    }

    private function connectSqlite(array $c): SQLite3
    {
        $path = $c['path'] ?? '';
        if (empty($path)) {
            throw new RuntimeException('sqlite connection requires a [path] in config.php.');
        }

        return new SQLite3($path);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function loadConfig(): void
    {
        if (!empty(self::$config)) return;

        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            throw new RuntimeException('config.php not found at: ' . dirname($path));
        }

        self::$config = require $path;
    }
}
