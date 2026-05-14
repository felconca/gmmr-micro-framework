<?php

class Router
{
    private Request  $request;
    private Response $response;

    /** Base directory where versioned folders live  e.g. controllers/ */
    private string $controllerDir;

    public function __construct(Request $request, Response $response, string $controllerDir)
    {
        $this->request       = $request;
        $this->response      = $response;
        $this->controllerDir = rtrim($controllerDir, '/');
    }

    /**
     * Resolve and dispatch the incoming request.
     *
     * URL shape (via .htaccess  ?x=<path>):
     *
     *   Both styles work and can be mixed freely:
     *
     *   WITH .php in the URL (explicit file):
     *     /api/v1/auth/login.php             → controllers/v1/auth/login.php   → index()
     *     /api/v1/auth/login.php/register    → controllers/v1/auth/login.php   → register()
     *     /api/v1/auth/login.php/reset?t=x   → controllers/v1/auth/login.php   → reset()
     *
     *   WITHOUT .php (implicit, router finds the file):
     *     /api/v1/auth/login                 → controllers/v1/auth/login.php   → index()
     *     /api/v1/auth/login/register        → controllers/v1/auth/login.php   → register()
     *     /api/v1/orders/create?ex=1&o=2     → controllers/v1/orders/create.php → index()  (or create())
     *
     *   The version segment (v1, v2 …) is just a folder — add as many as you need.
     *   Query-string params always work on any URL shape.
     */
    public function dispatch(): void
    {
        $this->cors();

        $raw      = $_REQUEST['x'] ?? '/';
        $segments = $this->parseSegments($raw);

        // ── Strategy 1: explicit .php in the URL ─────────────────────────────
        // Find the segment that ends with .php, resolve that file, rest = method.
        foreach ($segments as $i => $seg) {
            if (str_ends_with(strtolower($seg), '.php')) {
                // Build path up to and including this segment
                $filePath = $this->controllerDir
                    . '/' . implode('/', array_slice($segments, 0, $i + 1));

                if (file_exists($filePath)) {
                    $method = $segments[$i + 1] ?? 'index';
                    $extra  = array_slice($segments, $i + 2);
                    $this->load($filePath, $method, $extra);
                    return;
                }
            }
        }

        // ── Strategy 2: implicit — walk from deepest to shallowest ───────────
        // Try  controllers/<seg1>/.../<segN>.php  with method = next segment.
        for ($split = count($segments); $split >= 1; $split--) {
            $filePath = $this->controllerDir
                . '/' . implode('/', array_slice($segments, 0, $split))
                . '.php';

            if (file_exists($filePath)) {
                $method = $segments[$split] ?? 'index';
                $extra  = array_slice($segments, $split + 1);
                $this->load($filePath, $method, $extra);
                return;
            }
        }

        $this->response->send(['status' => 404, 'error' => 'Endpoint not found'], 404);
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function load(string $filePath, string $methodName, array $extra): void
    {
        require_once $filePath;

        // Class name derived from the bare filename (no .php), StudlyCase + Controller
        // e.g.  login.php  →  LoginController
        //       user_profile.php  →  UserProfileController
        $className = $this->toStudly(basename($filePath, '.php')) . 'Controller';

        if (!class_exists($className)) {
            $this->response->send([
                'status' => 500,
                'error'  => "Controller class [{$className}] not found in " . basename($filePath),
            ], 500);
        }

        // Extra URL segments after the method become positional params:
        //   /v1/auth/login.php/reset/abc123  →  param0 = abc123
        $params = [];
        foreach ($extra as $i => $seg) {
            $params["param{$i}"] = $seg;
        }
        $this->request->setParams($params);

        $controller = new $className();

        if (method_exists($controller, 'boot')) {
            $controller->boot();
        }

        // Accept both camelCase and the raw segment casing
        $method = lcfirst($this->toStudly($methodName));
        if (!method_exists($controller, $method)) {
            $method = $methodName;
        }

        if (!method_exists($controller, $method)) {
            $this->response->send([
                'status' => 404,
                'error'  => "Method [{$method}] not found in [{$className}]",
            ], 404);
        }

        try {
            $controller->$method($this->request, $this->response);
        } catch (ValidationException $e) {
            $this->response->send(['status' => 422, 'errors' => $e->getErrors()], 422);
        } catch (Throwable $e) {
            $this->response->send(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    private function parseSegments(string $raw): array
    {
        $path = trim($raw, '/');
        if ($path === '') return ['index'];

        return array_values(
            array_filter(explode('/', $path), fn($s) => $s !== '')
        );
    }

    private function toStudly(string $value): string
    {
        // Strip .php suffix before converting so  login.php → Login not LoginPhp
        $value = preg_replace('/\.php$/i', '', $value);
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
