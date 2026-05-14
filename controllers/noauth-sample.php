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

            // Get pagination params (page, limit) from query; defaults: page=1, limit=20
            $page = (int)$request->query('page', 1);
            $limit = (int)$request->query('limit', 20);
            $page = max(1, $page);
            $limit = max(1, min(100, $limit)); // limit not above 100

            $offset = ($page - 1) * $limit;

            // Query total count for pagination
            $result = $conn->query("SELECT COUNT(*) AS total FROM users");
            $row = $result->fetch_assoc();
            $total = isset($row['total']) ? (int)$row['total'] : 0;

            // Fetch paginated data
            $stmt = $conn->prepare("SELECT * FROM users LIMIT ? OFFSET ?");
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $res = $stmt->get_result();

            $users = [];
            while ($user = $res->fetch_assoc()) {
                $users[] = $user;
            }
            $stmt->close();

            // Respond with paginated result
            $response([
                'data'  => $users,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit
            ], 200);

            // TODO: implement index logic

            $response(['status' => 200, 'data' => []]);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
}
