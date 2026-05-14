<?php

/**
 * api.php  –  Entry point (same file the .htaccess already points to)
 *
 * URL shape  →  Controller mapping
 * ─────────────────────────────────────────────────────────────────────────
 *  /api/tokens/refreshTokens          →  controllers/tokens.php  → refreshTokens()
 *  /api/invoices/create               →  controllers/invoices.php → create()
 *  /api/invoices/nonpharma/create     →  controllers/invoices/nonpharma.php → create()
 *  /api/vendors/create?refdb=wgfin    →  controllers/vendors.php → create()
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Query-string params (?ex=1&o=2) are always available via $request->query('ex').
 * Body params (JSON or form) are available via $request->input('key') or $request->all().
 * URL segment extras are available via $request->param('param0'), param('param1'), …
 */

declare(strict_types=1);

// ── Autoload core classes ────────────────────────────────────────────────────
require_once __DIR__ . '/core/ValidationException.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/TokenService.php';
require_once __DIR__ . '/core/MiddleWare.php';
require_once __DIR__ . '/core/BaseController.php';
require_once __DIR__ . '/core/Router.php';


// ── Optional: load your existing helpers / env ───────────────────────────────
// require_once __DIR__ . '/env.php';
// require_once __DIR__ . '/redis.config.php';
// include    __DIR__ . '/qbo.model.php';
// include    __DIR__ . '/qbo.helpers.php';

// ── Boot & dispatch ──────────────────────────────────────────────────────────
$request  = new Request();
$response = new Response();
$router   = new Router($request, $response, __DIR__ . '/controllers');

$router->dispatch();
