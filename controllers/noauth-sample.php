<?php

require_once __DIR__ . '/../core/BaseController.php';

/**
 * NoauthSampleController
 *
 * Generated : 2026-05-14
 * URL prefix : /api/noauth-sample.php
 * Auth       : Public — no auth required
 */
class NoauthSampleController extends BaseController
{
    // ── GET /api/noauth-sample.php/index ─────────────────────────────────────
    public function index(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);

        try {
            $conn = $this->db();

            // TODO: implement index logic

            $response(['data' => []], 200);
        } catch (Throwable $e) {
            $response(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
