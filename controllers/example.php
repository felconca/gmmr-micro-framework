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
            while ($data = $res->fetch_assoc()) {
                $users[] = $data;
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

    // ── POST /api/example.php/login ────────────────────────────────────────
    public function login(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);

        try {
            $conn = $this->db();

            // Get and sanitize input
            $username = trim($request->input('username', ''));
            $password = trim($request->input('password', ''));

            if (empty($username) || empty($password)) {
                $response([
                    'error' => true,
                    'message' => 'Username and password are required.'
                ], 422);
                return;
            }

            $md5pass = md5($password);

            // Query for user and join px_data
            $stmt = $conn->prepare(
                "SELECT 
                    u.UserName, 
                    u.PassWD, 
                    u.PxRID,
                    d.FirstName, 
                    d.LastName, 
                    d.MiddleName, 
                    d.namesuffix
                 FROM users u
                 LEFT JOIN px_data d ON u.PxRID = d.PxRID
                 WHERE u.UserName = ?"
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            if (!$user || $user['PassWD'] !== $md5pass) {
                $response([
                    'error' => true,
                    'message' => 'Invalid username or password.'
                ], 401);
                return;
            }


            // Build payload for the token
            $payload = [
                'sub'        => $user['UserName'],
                'pxrid'      => $user['PxRID'],
                'firstname'  => $user['FirstName'],
                'lastname'   => $user['LastName'],
                'middlename' => $user['MiddleName'],
                'namesuffix' => $user['namesuffix']
            ];

            $tokens = $this->tokens()->issue($payload);

            // Return user info and tokens (removing sensitive info)
            $response([
                'success' => true,
                'tokens'  => $tokens,
                'user' => [
                    'UserName'   => $user['UserName'],
                    'FirstName'  => $user['FirstName'],
                    'LastName'   => $user['LastName'],
                    'MiddleName' => $user['MiddleName'],
                    'namesuffix' => $user['namesuffix']
                ]
            ], 200);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
}
