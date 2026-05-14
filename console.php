#!/usr/bin/env php
<?php

/**
 * console.php  –  Code generator CLI
 *
 * Usage:
 *   php console.php gen:controller Cylinders
 *   php console.php gen:controller v1/auth/Login
 *   php console.php gen:controller v1/orders/Items --methods=list,create,update,delete
 *   php console.php gen:controller v1/auth/Login --force
 *   php console.php gen:controller v1/auth/Forgot --no-auth
 *   php console.php gen:controller v1/auth/Forgot --no-auth --methods=forgot,reset,verify
 */

define('BASE_DIR',        __DIR__);
define('CONTROLLERS_DIR', BASE_DIR . '/controllers');
define('CORE_DIR',        BASE_DIR . '/core');

$args    = array_slice($argv, 1);
$command = $args[0] ?? null;

if (!$command) {
    Output::help();
    exit(0);
}

match (true) {
    str_starts_with($command, 'gen:controller') => ControllerGenerator::controller($args),
    $command === 'list'                         => Output::help(),
    default                                     => Output::error("Unknown command [{$command}]. Run  php console.php list  for help."),
};

// ── Generator ─────────────────────────────────────────────────────────────────

class ControllerGenerator
{
    public static function controller(array $args): void
    {
        $name = $args[1] ?? null;

        if (!$name) {
            Output::error('Controller name is required.');
            Output::line('  Usage: php console.php gen:controller Name');
            Output::line('         php console.php gen:controller v1/auth/Login');
            exit(1);
        }

        $flags   = self::parseFlags(array_slice($args, 2));
        $force   = isset($flags['force']);
        $noAuth  = isset($flags['no-auth']);
        $methods = isset($flags['methods'])
            ? array_map('trim', explode(',', $flags['methods']))
            : ['index'];

        $parts     = explode('/', str_replace('\\', '/', $name));
        $rawName   = array_pop($parts);
        $subFolder = implode('/', $parts);

        $fileName  = strtolower($rawName);
        $className = self::toStudly($rawName) . 'Controller';

        $dir      = CONTROLLERS_DIR . ($subFolder ? '/' . $subFolder : '');
        $filePath = $dir . '/' . $fileName . '.php';

        $depth    = count(array_filter(explode('/', $subFolder))) + 1;
        $corePath = str_repeat('../', $depth) . 'core/BaseController.php';

        if (file_exists($filePath) && !$force) {
            Output::warn("File already exists: " . self::relativePath($filePath));
            Output::line('  Use --force to overwrite.');
            exit(1);
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            Output::info('Created directory: ' . self::relativePath($dir));
        }

        $content = self::buildControllerContent($className, $corePath, $methods, $subFolder, $fileName, $noAuth);

        file_put_contents($filePath, $content);

        Output::success('Controller created: ' . self::relativePath($filePath));
        Output::line('  Class   : ' . $className);
        Output::line('  Methods : ' . implode(', ', $methods));
        Output::line('  Auth    : ' . ($noAuth ? "\033[33mNo (public endpoint)\033[0m" : "\033[32mYes (Middleware::auth)\033[0m"));
        Output::line('');
        Output::line('  URL     : /api/' . ($subFolder ? $subFolder . '/' : '') . $fileName . '.php/{method}');
    }

    // ── Template builder ──────────────────────────────────────────────────────

    private static function buildControllerContent(
        string $className,
        string $corePath,
        array  $methods,
        string $subFolder,
        string $fileName,
        bool   $noAuth = false,
    ): string {
        $urlPath     = ($subFolder ? $subFolder . '/' : '') . $fileName;
        $methodsCode = '';

        foreach ($methods as $method) {
            $httpHint = match (strtolower($method)) {
                'list', 'index', 'show', 'find', 'get'  => 'GET',
                'create', 'store', 'login', 'register'   => 'POST',
                'forgot', 'reset', 'verify', 'refresh'   => 'POST',
                'update'                                  => 'PUT',
                'delete', 'destroy'                       => 'DELETE',
                default                                   => 'GET',
            };

            if ($noAuth) {
                // Public endpoint — method guard only, no auth
                $middlewareLines = "        Middleware::method('{$httpHint}', \$request, \$response);";
                $connLine        = "\$conn = \$this->db();";
            } else {
                // Protected endpoint — method guard + auth
                $middlewareLines = "        Middleware::method('{$httpHint}', \$request, \$response);\n        \$user = Middleware::auth(\$request, \$response);";
                $connLine        = "\$conn = \$this->db();";
            }

            $methodsCode .= <<<PHP

    // ── {$httpHint} /api/{$urlPath}.php/{$method} ─────────────────────────────────────
    public function {$method}(Request \$request, Response \$response): void
    {
{$middlewareLines}

        try {
            \$conn = \$this->db();

            // TODO: implement {$method} logic

            \$response(['data' => []], 200);

        } catch (Throwable \$e) {
            \$response(['error' => true, 'message' => \$e->getMessage()], 500);
        }
    }
PHP;
        }

        $date    = date('Y-m-d');
        $authTag = $noAuth ? 'Public — no auth required' : 'Protected — requires valid token';

        return <<<PHP
<?php

require_once __DIR__ . '/{$corePath}';

/**
 * {$className}
 *
 * Generated : {$date}
 * URL prefix : /api/{$urlPath}.php
 * Auth       : {$authTag}
 */
class {$className} extends BaseController
{{$methodsCode}
}
PHP;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function parseFlags(array $args): array
    {
        $flags = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = ltrim($arg, '-');
                if (str_contains($arg, '=')) {
                    [$key, $val] = explode('=', $arg, 2);
                    $flags[$key] = $val;
                } else {
                    $flags[$arg] = true;
                }
            }
        }
        return $flags;
    }

    private static function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private static function relativePath(string $path): string
    {
        return str_replace(BASE_DIR . '/', '', $path);
    }
}

// ── Output helpers ────────────────────────────────────────────────────────────

class Output
{
    public static function success(string $msg): void
    {
        echo "\033[32m✔  {$msg}\033[0m" . PHP_EOL;
    }
    public static function error(string $msg): void
    {
        echo "\033[31m✘  {$msg}\033[0m" . PHP_EOL;
        exit(1);
    }
    public static function warn(string $msg): void
    {
        echo "\033[33m⚠  {$msg}\033[0m" . PHP_EOL;
    }
    public static function info(string $msg): void
    {
        echo "\033[34mℹ  {$msg}\033[0m" . PHP_EOL;
    }
    public static function line(string $msg): void
    {
        echo $msg . PHP_EOL;
    }

    public static function help(): void
    {
        echo <<<HELP

\033[33m PHP API Generator\033[0m

\033[32mUsage:\033[0m
  php console.php <command> [arguments] [options]

\033[32mAvailable commands:\033[0m
  \033[36mgen:controller\033[0m  Generate a new controller file

\033[32mExamples:\033[0m
  php console.php gen:controller Cylinders
  php console.php gen:controller v1/auth/Login
  php console.php gen:controller v1/orders/Items --methods=list,create,update,delete
  php console.php gen:controller v1/reports/Sales --methods=daily,weekly,monthly
  php console.php gen:controller v1/auth/Login --force
  php console.php gen:controller v1/auth/Forgot --no-auth
  php console.php gen:controller v1/auth/Forgot --no-auth --methods=forgot,reset,verify

\033[32mOptions:\033[0m
  \033[36m--methods=a,b,c\033[0m  Comma-separated list of methods to generate (default: index)
  \033[36m--force\033[0m          Overwrite file if it already exists
  \033[36m--no-auth\033[0m        Skip Middleware::auth — generates a public endpoint

\033[32mAuth behaviour:\033[0m
  Default              →  Middleware::method + Middleware::auth (protected)
  --no-auth            →  Middleware::method only (public)

\033[32mGenerated URL shape:\033[0m
  gen:controller Cylinders                         →  /api/cylinders.php/index
  gen:controller v1/auth/Login                     →  /api/v1/auth/login.php/index  (protected)
  gen:controller v1/auth/Forgot --no-auth          →  /api/v1/auth/forgot.php/index  (public)
  gen:controller v1/auth/Forgot --no-auth --methods=forgot,reset,verify

HELP;
    }
}
