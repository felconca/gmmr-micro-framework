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
    // ── GET /api/example or example.php/index or / ────────────────────────────────────────
    public function index(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        Middleware::auth($request, $response);

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
    /**
     * Get an order by ID.
     * GET /api/noauth-sample.php/orders/get
     * Query: order_id (required)
     */
    public function editOrder(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);

        try {
            $orderId = $request->query('order_id');
            if (empty($orderId) || !is_numeric($orderId)) {
                $response([
                    'error' => true,
                    'message' => 'order_id parameter is required and must be numeric.'
                ], 400);
                return;
            }

            $conn = $this->db();
            $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $res = $stmt->get_result();
            $order = $res->fetch_assoc();
            $stmt->close();

            if ($order) {
                $response($order, 200);
            } else {
                $response([
                    'error' => true,
                    'message' => 'Order not found'
                ], 404);
            }
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Add a new order.
     * POST /api/noauth-sample.php/orders/add
     * Body: user_id (required, int), total_amount (required, decimal), status (optional)
     */
    public function addOrder(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);

        try {
            $fields = $request->validate([
                'user_id'      => 'required|integer|min:1',
                'total_amount' => 'required|numeric|min:0',
                'status'       => 'string|nullable|in:pending,processing,completed,cancelled'
            ]);

            $status = $fields['status'] ?? 'pending';

            $conn = $this->db();
            $stmt = $conn->prepare(
                "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)"
            );
            $stmt->bind_param(
                'ids',
                $fields['user_id'],
                $fields['total_amount'],
                $status
            );
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success'   => true,
                    'order_id'  => $stmt->insert_id
                ], 201);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'Failed to add order'
                ], 500);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->getMessage()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update an order.
     * PUT /api/noauth-sample.php/orders/update
     * Body: order_id (required, int), user_id (int), total_amount (decimal), status (string)
     */
    public function updateOrder(Request $request, Response $response): void
    {
        Middleware::method('PUT', $request, $response);

        try {
            $fields = $request->validate([
                'order_id'     => 'required|integer|min:1',
                'user_id'      => 'integer|min:1',
                'total_amount' => 'numeric|min:0',
                'status'       => 'string|nullable|in:pending,processing,completed,cancelled'
            ]);

            $updates = [];
            $params  = [];
            $types   = '';

            if (isset($fields['user_id'])) {
                $updates[] = 'user_id = ?';
                $types    .= 'i';
                $params[]  = $fields['user_id'];
            }
            if (isset($fields['total_amount'])) {
                $updates[] = 'total_amount = ?';
                $types    .= 'd';
                $params[]  = $fields['total_amount'];
            }
            if (isset($fields['status'])) {
                $updates[] = 'status = ?';
                $types    .= 's';
                $params[]  = $fields['status'];
            }

            if (empty($updates)) {
                $response([
                    'error' => true,
                    'message' => 'No fields to update.'
                ], 400);
                return;
            }

            $types .= 'i';
            $params[] = $fields['order_id'];

            $setClause = implode(', ', $updates);

            $conn = $this->db();
            $stmt = $conn->prepare("UPDATE orders SET $setClause WHERE order_id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true,
                    'updated' => $stmt->affected_rows
                ], 200);
            } else {
                $response([
                    'error' => true,
                    'message' => 'No order updated or order not found'
                ], 404);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->getMessage()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete an order.
     * DELETE /api/noauth-sample.php/orders/delete
     * Body or Query: order_id (required, int)
     */
    public function deleteOrder(Request $request, Response $response): void
    {
        Middleware::method('DELETE', $request, $response);

        try {
            $orderId = $request->query('order_id');
            if (empty($orderId) || !is_numeric($orderId)) {
                $response([
                    'error' => true,
                    'message' => 'order_id parameter is required and must be numeric.'
                ], 400);
                return;
            }

            $conn = $this->db();
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true,
                    'deleted' => $stmt->affected_rows
                ], 200);
            } else {
                $response([
                    'error' => true,
                    'message' => 'Order not found or could not be deleted'
                ], 404);
            }
            $stmt->close();
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    // ── GET /api/example or example.php/products ─────────────────────────────
    public function products(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        Middleware::auth($request, $response);

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
    /**
     * Get a product by ID.
     * GET /api/example.php/products/get
     */
    public function editProduct(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        Middleware::auth($request, $response);

        try {
            $productId = $request->query('product_id');
            if (empty($productId) || !is_numeric($productId)) {
                $response([
                    'error' => true,
                    'message' => 'product_id parameter is required and must be numeric.'
                ], 400);
                return;
            }

            $conn = $this->db();
            $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $product = $res->fetch_assoc();
            $stmt->close();

            if ($product) {
                $response($product, 200);
            } else {
                $response([
                    'error' => true,
                    'message' => 'Product not found'
                ], 404);
            }
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Add a new product.
     * POST /api/example.php/products/add
     */
    public function addProduct(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);
        Middleware::auth($request, $response);

        try {
            $fields = $request->validate([
                'product_name'   => 'required|string|min:1|max:150',
                'description'    => 'string|nullable|max:65535',
                'price'          => 'required|numeric|min:0',
                'stock_quantity' => 'required|integer|min:0',
                'sku'            => 'string|nullable|max:100'
            ]);

            $conn = $this->db();
            $stmt = $conn->prepare(
                "INSERT INTO products (product_name, description, price, stock_quantity, sku) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'ssdds',
                $fields['product_name'],
                $fields['description'],
                $fields['price'],
                $fields['stock_quantity'],
                $fields['sku']
            );

            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success'     => true,
                    'product_id'  => $stmt->insert_id
                ], 201);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'Failed to add product'
                ], 500);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->getMessage()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update product.
     * PUT /api/example.php/products/update
     */
    public function updateProduct(Request $request, Response $response): void
    {
        Middleware::method('PUT', $request, $response);
        Middleware::auth($request, $response);

        try {
            $fields = $request->validate([
                'product_id'     => 'required|integer|min:1',
                'product_name'   => 'string|min:1|max:150',
                'description'    => 'string|nullable|max:65535',
                'price'          => 'numeric|min:0',
                'stock_quantity' => 'integer|min:0',
                'sku'            => 'string|nullable|max:100'
            ]);


            $conn = $this->db();

            $stmt = $conn->prepare(
                "UPDATE products SET product_name = ?, description = ?, price = ?, stock_quantity = ?, sku = ? WHERE product_id = ?"
            );
            $stmt->bind_param(
                'ssdisi',
                $fields['product_name'],
                $fields['description'],
                $fields['price'],
                $fields['stock_quantity'],
                $fields['sku'],
                $fields['product_id']
            );
            $stmt->execute();


            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true
                ], 200);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'No rows updated (check product_id or input the same as current data)'
                ], 404);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->getMessage()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete product.
     * DELETE /api/example.php/products/delete
     */
    public function deleteProduct(Request $request, Response $response): void
    {
        Middleware::method('DELETE', $request, $response);
        Middleware::auth($request, $response);

        try {
            $fields = $request->validate([
                'product_id' => 'required|integer|min:1',
            ]);

            $conn = $this->db();
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $fields['product_id']);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true
                ], 200);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'Product not found or already deleted'
                ], 404);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }



    // ── GET /api/example.php or example/order-items ─────────────────────────────
    public function orderItems(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        Middleware::auth($request, $response);

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
    /**
     * Add an order item.
     * POST /api/example.php/orderitems/add
     */
    public function addOrderItem(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);
        Middleware::auth($request, $response);

        try {
            // Validate input using request->validate()
            $fields = $request->validate([
                'order_id'   => 'required|integer|min:1',
                'product_id' => 'required|integer|min:1',
                'quantity'   => 'required|integer|min:1',
                'price'      => 'required|numeric|min:0'
            ]);

            $conn = $this->db();
            $stmt = $conn->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iiid',
                $fields['order_id'],
                $fields['product_id'],
                $fields['quantity'],
                $fields['price']
            );
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success'        => true,
                    'order_item_id'  => $stmt->insert_id
                ], 201);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'Failed to add order item'
                ], 500);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update an order item.
     * PUT /api/example.php/orderitems/update
     */
    public function updateOrderItem(Request $request, Response $response): void
    {
        Middleware::method('PUT', $request, $response);
        Middleware::auth($request, $response);

        try {
            // Validate presence of order_item_id, other fields optional but must be correct types if present
            $fields = $request->validate([
                'order_item_id' => 'required|integer|min:1',
                'quantity'      => 'integer|min:1',
                'price'         => 'numeric|min:0'
            ]);

            $updates = [];
            $params  = [];
            $types   = '';

            if (isset($fields['quantity'])) {
                $updates[] = 'quantity = ?';
                $types    .= 'i';
                $params[]  = $fields['quantity'];
            }

            if (isset($fields['price'])) {
                $updates[] = 'price = ?';
                $types    .= 'd';
                $params[]  = $fields['price'];
            }

            if (empty($updates)) {
                $response([
                    'error'   => true,
                    'message' => 'No valid fields to update.'
                ], 400);
                return;
            }

            $types    .= 'i';
            $params[]  = $fields['order_item_id'];

            $conn = $this->db();
            $sql  = "UPDATE order_items SET " . implode(", ", $updates) . " WHERE order_item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true
                ], 200);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'No rows updated (check order_item_id or input the same as current data)'
                ], 404);
            }
            $stmt->close();
        } catch (ValidationException $e) {
            $response([
                'error'   => true,
                'message' => 'Validation failed',
                'fields'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete an order item.
     * DELETE /api/example.php/orderitems/delete
     */
    public function deleteOrderItem(Request $request, Response $response): void
    {
        Middleware::method('DELETE', $request, $response);
        Middleware::auth($request, $response);

        try {
            $fields = $request->validate([
                'order_item_id' => 'required|integer|min:1',
            ]);

            $conn = $this->db();
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_item_id = ?");
            $stmt->bind_param("i", $fields['order_item_id']);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response([
                    'success' => true
                ], 200);
            } else {
                $response([
                    'error'   => true,
                    'message' => 'Order item not found or already deleted'
                ], 404);
            }
            $stmt->close();
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }


    // ── POST /api/example.php or example/login ────────────────────────────────────────
    public function login(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);

        try {
            $conn = $this->db();

            // Get and sanitize input
            $email = trim($request->input('email', ''));
            $password = trim($request->input('password', ''));

            if (empty($email) || empty($password)) {
                $response([
                    'error' => true,
                    'message' => 'Email and password are required.'
                ], 422);
                return;
            }

            // Prepare and execute query to get user by email
            $stmt = $conn->prepare(
                "SELECT user_id, first_name, last_name, email, password, phone, address FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            // For demo: compare plaintext passwords. Production: use password_verify()!
            // Here we assume passwords are stored hashed, so use password_verify.
            if (!$user || !password_verify($password, $user['password'])) {
                $response([
                    'error' => true,
                    'message' => 'Invalid email or password.'
                ], 401);
                return;
            }

            // Build payload for the token
            $payload = [
                'sub'        => $user['user_id'],
                'email'      => $user['email'],
                'firstname'  => $user['first_name'],
                'lastname'   => $user['last_name']
            ];

            $tokens = $this->tokens()->issue($payload);

            // Return user info and tokens (removing sensitive info)
            $response([
                'success' => true,
                'tokens'  => $tokens,
                'user' => [
                    'user_id'    => $user['user_id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'phone'      => $user['phone'],
                    'address'    => $user['address']
                ]
            ], 200);
        } catch (Throwable $e) {
            $response->error(['status' => 500, 'error' => $e->getMessage()]);
        }
    }
}
