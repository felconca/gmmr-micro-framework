<?php

/**
 * config.php  –  Central configuration
 *
 * Edit this file to match your environment.
 * Never commit real credentials to version control.
 */

return [

    // ── Timezone ─────────────────────────────────────────────────────────────
    'timezone' => 'Asia/Manila',

    // ── CORS ─────────────────────────────────────────────────────────────────
    'cors' => [
        'allowed_origins'    => '*',    // '*' or 'https://yourdomain.com'
        'allowed_methods'    => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'allowed_headers'    => 'Content-Type, X-Auth-Token, Origin, Authorization',
        'allow_credentials'  => true,
    ],

    // ── App ───────────────────────────────────────────────────────────────────
    'app' => [
        'name'  => 'My API',
        'debug' => true,   // true = include full error messages in responses
    ],

    // ── JWT ───────────────────────────────────────────────────────────────────
    'jwt' => [
        // Use a long random string — minimum 32 chars recommended.
        // Generate one with:  php -r "echo bin2hex(random_bytes(32));"
        'secret'        => '7f37f73bc55a5d9277c3f415166f2bfabc1698c5b858b45f69f32e60878411f7',

        'access_ttl'    => 900,        // access token lifetime  in seconds (15 min)
        'refresh_ttl'   => 604800,     // refresh token lifetime in seconds (7 days)

        'cookie_prefix' => 'app',      // cookies will be named  app_access_token / app_refresh_token
        'secure'        => false,       // set false for local http dev, true for production https
        'same_site'     => 'Strict',   // 'Strict' | 'Lax' | 'None'
    ],


    // ── Databases ─────────────────────────────────────────────────────────────
    // Add as many connections as you need.
    // The key is what you pass to $this->db('key') in a controller.
    //
    // Supported drivers:  mysqli  |  pgsql  |  sqlsrv  |  sqlite
    //
    // mysqli / pgsql / sqlsrv  →  requires host, user, password, name
    // sqlite                   →  only requires  path  (absolute path to .db file)
    //
    'db_default' => 'primary',   // key used when $this->db() is called with no argument

    'databases' => [

        'primary' => [
            'driver'   => 'mysqli',       // mysqli | pgsql | sqlsrv | sqlite
            'host'     => 'localhost',
            'port'     => 3306,
            'name'     => 'sample',
            'user'     => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',      // mysqli / pgsql only
        ],

        // Example: a second MySQL database
        // 'secondary' => [
        //     'driver'   => 'mysqli',
        //     'host'     => 'localhost',
        //     'port'     => 3306,
        //     'name'     => 'other_db',
        //     'user'     => 'db_user',
        //     'password' => 'db_password',
        //     'charset'  => 'utf8mb4',
        // ],

        // Example: PostgreSQL
        // 'analytics' => [
        //     'driver'   => 'pgsql',
        //     'host'     => 'localhost',
        //     'port'     => 5432,
        //     'name'     => 'analytics_db',
        //     'user'     => 'pg_user',
        //     'password' => 'pg_password',
        //     'charset'  => 'utf8',
        // ],

        // Example: SQL Server
        // 'legacy' => [
        //     'driver'   => 'sqlsrv',
        //     'host'     => '192.168.1.10',
        //     'port'     => 1433,
        //     'name'     => 'LegacyDB',
        //     'user'     => 'sa',
        //     'password' => 'sa_password',
        // ],

        // Example: SQLite
        // 'local' => [
        //     'driver' => 'sqlite',
        //     'path'   => __DIR__ . '/storage/local.db',
        // ],

    ],

    'mail' => [
        'driver'     => 'smtp',           // smtp | mail
        'host'       => 'smtp.gmail.com', //'smtp.hostinger.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   =>  'snoreply@yourdomain.com', //'noreply@yourdomain.com',   // your Hostinger email
        'password'   => '',
        'from_email' => 'noreply@yourdomain.com',
        'from_name'  => 'NOREPLY',
    ],

];
