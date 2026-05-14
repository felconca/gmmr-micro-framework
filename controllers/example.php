<?php

require_once __DIR__ . '/../core/BaseController.php';

/**
 * ExampleController
 *
 * Generated : 2026-05-14
 * URL prefix : /api/example.php
 */
class ExampleController extends BaseController
{
    // ── GET /api/example.php/index ────────────────────────────────────────
    public function index(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        $user = Middleware::auth($request, $response);

        try {
            $conn = $this->db();

            // TODO: implement index logic

            $response(['status' => 200, 'data' => []]);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
}
