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

            // Query total count for pagination from orders table
            $result = $conn->query("SELECT COUNT(*) AS total FROM orders");
            $row = $result->fetch_assoc();
            $total = isset($row['total']) ? (int)$row['total'] : 0;

            // Fetch paginated orders data
            $stmt = $conn->prepare("SELECT * FROM orders LIMIT ? OFFSET ?");
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $res = $stmt->get_result();

            $orders = [];
            while ($order = $res->fetch_assoc()) {
                $orders[] = $order;
            }
            $stmt->close();

            // Respond with paginated result
            $response([
                'data'  => $orders,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit
            ], 200);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
    // ── GET /api/noauth-sample.php/products ─────────────────────────────
    public function products(Request $request, Response $response): void
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

            // Query total count for pagination from products table
            $result = $conn->query("SELECT COUNT(*) AS total FROM products");
            $row = $result->fetch_assoc();
            $total = isset($row['total']) ? (int)$row['total'] : 0;

            // Fetch paginated products data
            $stmt = $conn->prepare("SELECT * FROM products LIMIT ? OFFSET ?");
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $res = $stmt->get_result();

            $products = [];
            while ($product = $res->fetch_assoc()) {
                $products[] = $product;
            }
            $stmt->close();

            // Respond with paginated result
            $response([
                'data'  => $products,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit
            ], 200);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
    // ── GET /api/noauth-sample.php/order-items ─────────────────────────────
    public function orderItems(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);

        try {
            $conn = $this->db();

            // Get required order_id from query
            $orderId = $request->query('order_id');
            if (empty($orderId) || !is_numeric($orderId)) {
                $response([
                    'error' => true,
                    'message' => 'order_id parameter is required and must be numeric.'
                ], 400);
                return;
            }

            // Pagination params (optional)
            $page = (int)$request->query('page', 1);
            $limit = (int)$request->query('limit', 20);
            $page = max(1, $page);
            $limit = max(1, min(100, $limit)); // limit not above 100
            $offset = ($page - 1) * $limit;

            // Get total order_items count for this order
            $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM order_items WHERE order_id = ?");
            $stmtTotal->bind_param('i', $orderId);
            $stmtTotal->execute();
            $resTotal = $stmtTotal->get_result();
            $row = $resTotal->fetch_assoc();
            $total = isset($row['total']) ? (int)$row['total'] : 0;
            $stmtTotal->close();

            // Fetch paginated order_items joined with order and product
            $query = "
                SELECT 
                    oi.*, 

                    o.order_id AS order_order_id, 
                    o.order_date AS order_order_date, 
                    o.status AS order_status, 

                    p.product_id AS product_product_id,
                    p.product_name AS product_name,
                    p.description AS product_description,
                    p.price AS product_price

                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.order_id
                INNER JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?
                LIMIT ? OFFSET ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iii', $orderId, $limit, $offset);
            $stmt->execute();
            $res = $stmt->get_result();

            $orderItems = [];
            while ($row = $res->fetch_assoc()) {
                // Structure the order info as a nested array
                $orderItem = $row;
                $orderItem['order'] = [
                    'order_id'     => $row['order_order_id'],
                    'order_date'   => $row['order_order_date'],
                    'status'       => $row['order_status'],
                ];
                $orderItem['product'] = [
                    'product_id'   => $row['product_product_id'],
                    'name'         => $row['product_name'],
                    'description'  => $row['product_description'],
                    'price'        => $row['product_price'],
                ];
                unset(
                    $orderItem['order_order_id'],
                    $orderItem['order_order_date'],
                    $orderItem['order_status'],
                    $orderItem['product_product_id'],
                    $orderItem['product_name'],
                    $orderItem['product_description'],
                    $orderItem['product_price']
                );
                $orderItems[] = $orderItem;
            }
            $stmt->close();

            $response([
                'data'  => $orderItems,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'order_id' => (int)$orderId
            ], 200);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
}
