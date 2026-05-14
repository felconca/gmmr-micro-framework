# PHP API Micro-Framework

A lightweight, zero-dependency PHP REST API framework. No Composer, no external libraries — pure PHP 8.1+. Built for shared hosting environments like Hostinger.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Directory Structure](#directory-structure)
- [Configuration](#configuration)
- [Routing](#routing)
- [Controllers](#controllers)
- [Request](#request)
- [Response](#response)
- [Validation](#validation)
- [Authentication & JWT](#authentication--jwt)
- [Middleware](#middleware)
- [Database](#database)
- [Services](#services)
- [CLI Generator](#cli-generator)
- [Cron Jobs](#cron-jobs)
- [Email](#email)
- [Logging](#logging)
- [Deployment on Hostinger](#deployment-on-hostinger)

---

## Requirements

- PHP 8.1 or higher
- MySQLi extension enabled
- Apache with mod_rewrite enabled (or Nginx equivalent)
- No Composer required

---

## Installation

1. Upload all files to your server (e.g. public_html/api/)
2. Make sure .htaccess is in the same folder as api.php
3. Copy config.php and fill in your database credentials and JWT secret
4. Done — no build step, no dependency install

```
public_html/
└── api/
    ├── .htaccess
    ├── api.php
    ├── config.php
    ├── console.php
    ├── core/
    ├── controllers/
    ├── services/
    └── cron/
```

---

## Directory Structure

```
api/
├── .htaccess                   — URL rewriting (do not modify)
├── index.php                   — Entry point
├── config.php                  — All configuration
├── console.php                 — CLI code generator
│
├── core/
│   ├── Request.php             — Input, validation
│   ├── Response.php            — JSON output
│   ├── Router.php              — URL to controller dispatch
│   ├── BaseController.php      — Shared DB, config, token access
│   ├── Middleware.php          — Auth, role, method guards
│   ├── TokenService.php        — JWT generate and verify (HS256)
│   └── ValidationException.php
│
├── controllers/                — Check examples inside
|   └── example.php             — Can create file directly
│   └── v1/
│       ├── auth/
│       │   └── login.php       LoginController
│       └── orders/
│           └── orders.php      OrdersController
│
├── services/                   — Empty by default
│   ├── LoginService.php        — Login attempts, IP, device
│   ├── LogService.php          — User activity logging
│   ├── MailService.php         — Raw SMTP email sender
│   └── PasswordResetService.php — Password reset and account activation
│
└── cron/           — Empty by default
    └── cron.php    — Scheduled jobs
```

---

## Configuration

All settings live in config.php. Edit this file before deploying.

```php
return [

    'timezone' => 'Asia/Manila',

    'app' => [
        'name'  => 'My API',
        'debug' => false,
    ],

    'jwt' => [
        // Generate: php -r "echo bin2hex(random_bytes(32));"
        'secret'        => 'your-long-random-secret-here',
        'access_ttl'    => 900,        // 15 minutes
        'refresh_ttl'   => 604800,     // 7 days
        'cookie_prefix' => 'app',
        'secure'        => true,       // false for local HTTP dev
        'same_site'     => 'Strict',
    ],

    'db_default' => 'primary', // $this->db()

    //Usage:  $this->db('primary');
    'databases' => [
        'primary' => [
            'driver'   => 'mysqli',    // mysqli | pgsql | sqlsrv | sqlite
            'host'     => 'localhost',
            'port'     => 3306,
            'name'     => 'my_database',
            'user'     => 'db_user',
            'password' => 'db_password',
            'charset'  => 'utf8mb4',
        ],
        // 'secondary' => [ 'driver' => 'mysqli', ... ],
        // 'analytics' => [ 'driver' => 'pgsql',  ... ],
        // 'local'     => [ 'driver' => 'sqlite', 'path' => __DIR__ . '/storage/db.sqlite' ],
    ],

    'mail' => [
        'driver'     => 'smtp',
        'host'       => 'smtp.google.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'noreply@yourdomain.com',
        'password'   => 'email_password',
        'from_email' => 'noreply@yourdomain.com',
        'from_name'  => 'My App',
    ],

    'cors' => [
        'allowed_origins'   => '*',
        'allowed_methods'   => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'allowed_headers'   => 'Content-Type, X-Auth-Token, Origin, Authorization',
        'allow_credentials' => true,
    ],
];
```

---

## Routing

### URL Shape

```
/api/{version}/{folder}/{file}.php/{method}?param=value
/api/{version}/{file}.php/{method}?param=value
/api/{file}.php/{method}?param=value
```

### Examples

| HTTP   | URL                                   | File                             | Method     |
| ------ | ------------------------------------- | -------------------------------- | ---------- |
| POST   | /api/v1/auth/login.php/login          | controllers/v1/auth/login.php    | login()    |
| GET    | /api/v1/orders/orders.php/list?page=1 | controllers/v1/orders/orders.php | list()     |
| POST   | /api/v1/auth/login.php/register       | controllers/v1/auth/login.php    | register() |
| DELETE | /api/v1/orders/orders.php/delete      | controllers/v1/orders/orders.php | delete()   |
| GET    | /api/example.php/index                | controllers/example.php          | index()    |
| GET    | /api/noauth-sample.php/index          | controllers/noauth-sample.php    | index()    |

### Rules

- The version segment (v1, v2, version1) is just a folder — name it anything
- .php in the URL is optional — /login.php/login and /login/login both work
- Query string params (?page=1&limit=50) always work on any URL
- File name to class name: login.php becomes LoginController, user_profile.php becomes UserProfileController

---

## Controllers

Every controller extends BaseController and lives in the controllers/ folder.

```php
<?php

require_once __DIR__ . '/../../core/BaseController.php';

class OrdersController extends BaseController
{
    // GET /api/v1/orders/orders.php/list
    public function list(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        $user = Middleware::auth($request, $response);

        try {
            $conn = $this->db();

            $stmt = $conn->prepare("SELECT * FROM orders WHERE deleted = 0");
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response($rows, 200);

        } catch (Throwable $e) {
            $response(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    // POST /api/v1/orders/orders.php/create
    public function create(Request $request, Response $response): void
    {
        Middleware::method('POST', $request, $response);
        $user = Middleware::auth($request, $response);

        try {
            $data = $request->validate([
                'customer_id' => 'required|numeric|min:1',
                'amount'      => 'required|numeric',
            ]);

            $conn      = $this->db();
            $createdBy = (int) $user['id'];

            $stmt = $conn->prepare("INSERT INTO orders (customer_id, amount, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param('idi', $data['customer_id'], $data['amount'], $createdBy);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            $response(['success' => true, 'message' => 'Order created.', 'id' => $newId], 201);

        } catch (ValidationException $e) {
            $response(['error' => true, 'message' => $e->getErrors()], 422);
        } catch (Throwable $e) {
            $response(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
```

### require_once Depth

```php
// controllers/orders.php           (1 level)
require_once __DIR__ . '/../core/BaseController.php';

// controllers/v1/orders.php        (2 levels)
require_once __DIR__ . '/../../core/BaseController.php';

// controllers/v1/orders/orders.php (3 levels)
require_once __DIR__ . '/../../../core/BaseController.php';
```

The CLI generator calculates this automatically.

---

## Request

```php
// Query string: ?page=1&limit=50
$page  = $request->query('page', 1);
$limit = $request->query('limit', 50);

// Body / POST / JSON
$name  = $request->input('name');
$email = $request->input('email');

// All body data as array
$all = $request->all();

// HTTP method
$method = $request->method();   // GET, POST, PUT, etc.

// Request header
$auth = $request->header('Authorization');

// URL segment param e.g. /orders/123 param0 = 123
$id = $request->param('param0');
```

### GET vs POST

- GET and DELETE read from $\_GET (query string)
- POST, PUT, PATCH read from request body (JSON or form-encoded)
- $request->validate() and $request->all() automatically read the right source

---

## Response

The response object is callable:

```php
$response($data, 200);
$response(['success' => true, 'id' => $newId], 201);

// Shorthand helpers
$response->ok($data);           // 200
$response->created($data);      // 201
$response->noContent();         // 204
$response->badRequest($data);   // 400
$response->unauthorized($data); // 401
$response->forbidden($data);    // 403
$response->notFound($data);     // 404
$response->error($data);        // 500
```

### Rules

- HTTP status code always goes in the second argument — never as a key inside the array
- Never put status as a key inside the response array
- Response always exits — no code runs after $response()

```php
// Correct
$response(['success' => true, 'message' => 'Done'], 200);

// Wrong — status inside array
$response(['status' => 200, 'success' => true]);
```

---

## Validation

```php
$data = $request->validate([
    'name'      => 'required|min:2|max:100',
    'email'     => 'required|email',
    'age'       => 'required|integer|min:1',
    'price'     => 'required|float',
    'status'    => 'required|in:active,pending,disabled',
    'role'      => 'not_in:superadmin',
    'website'   => 'url',
    'code'      => 'alpha_num',
    'dob'       => 'required|date',               // Y-m-d
    'scheduled' => 'required|datetime',           // Y-m-d H:i:s
    'opens_at'  => 'required|time',               // H:i or H:i:s
    'start'     => 'after:2024-01-01',
    'end'       => 'before:2030-12-31',
    'notes'     => '',                            // optional, no rules
]);
```

### All Rules

| Rule            | Description                             |
| --------------- | --------------------------------------- |
| required        | Must be present and not empty           |
| string          | Must be a string                        |
| numeric         | Must be a number                        |
| integer         | Must be a whole number                  |
| float           | Must be a decimal number                |
| boolean         | Must be true/false/1/0                  |
| email           | Valid email format                      |
| url             | Valid URL format                        |
| alpha           | Letters only                            |
| alpha_num       | Letters and numbers only                |
| alpha_dash      | Letters, numbers, dashes, underscores   |
| min:{n}         | Length / value / array count >= n       |
| max:{n}         | Length / value / array count <= n       |
| in:{a},{b}      | Value must be one of listed options     |
| not_in:{a},{b}  | Value must NOT be one of listed options |
| date            | Valid date Y-m-d                        |
| datetime        | Valid datetime Y-m-d H:i:s              |
| time            | Valid time H:i or H:i:s                 |
| before:{date}   | Must be before the given date           |
| after:{date}    | Must be after the given date            |
| regex:{pattern} | Must match regex pattern                |
| array           | Must be an array                        |

### Optional Fields

Fields without required are fully skipped if not present in the payload:

```php
$data = $request->validate([
    'id'     => 'required|numeric|min:1',  // always validated
    'notes'  => 'string',                  // only validated if sent
    'date'   => 'date',                    // only validated if sent
]);
```

### Array and Wildcard Validation

```php
$data = $request->validate([
    'items'          => 'required|array|min:1',
    'items.*.id'     => 'required|integer|min:1',
    'items.*.qty'    => 'required|numeric|min:1',
    'items.*.status' => 'required|in:pending,approved',
]);
```

---

## Authentication and JWT

### Issue Tokens (Login)

```php
$tokens = $this->tokens()->issue([
    'id'       => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
]);

$response(['message' => 'Login successful', 'tokens' => $tokens], 200);
```

### Protect Endpoints

```php
$user = Middleware::auth($request, $response);
// $user['id'], $user['role'], etc.
```

### Token via Header

```
Authorization: Bearer <access_token>
```

The framework checks the Authorization header first, then falls back to the cookie. Both browser and mobile work from the same API.

### Refresh and Revoke

```php
$tokens = $this->tokens()->refresh();  // issue new pair from refresh token
$this->tokens()->revoke();             // clear both cookies (logout)
```

---

## Middleware

```php
// HTTP method guard
Middleware::method('POST', $request, $response);

// Auth guard — returns decoded token payload
$user = Middleware::auth($request, $response);

// Role guard
$user = Middleware::role(['admin', 'superadmin'], $request, $response);

// Guest guard — blocks logged-in users
Middleware::guest($request, $response);
```

---

## Database

### Connect

```php
$conn = $this->db();                    // default connection
$conn = $this->db('secondary');         // named connection from config
$conn = $this->db($request->input('db')); // dynamic from request
```

### Queries

Always use prepared statements.

```php
// SELECT one
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// SELECT many
$stmt = $conn->prepare("SELECT * FROM orders WHERE status = ? AND deleted = 0");
$stmt->bind_param('s', $status);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// INSERT
$stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
$stmt->bind_param('ss', $name, $email);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

// UPDATE
$stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
$stmt->bind_param('si', $name, $id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
```

### bind_param Types

| Char | Type           |
| ---- | -------------- |
| i    | integer        |
| s    | string         |
| d    | double / float |
| b    | blob           |

### Supported Drivers

| Driver     | Config value |
| ---------- | ------------ |
| MySQL      | mysqli       |
| PostgreSQL | pgsql        |
| SQL Server | sqlsrv       |
| SQLite     | sqlite       |

---

## Services

Include with require_once at the top of your controller file.

### LogService

```php
require_once __DIR__ . '/../../services/LogService.php';

LogService::login($conn, $user['id'], $ip, $device);
LogService::logout($conn, $user['id']);
LogService::create($conn, $user['id'], 'Created delivery DLV-20260501-001');
LogService::update($conn, $user['id'], 'Updated cylinder ID 42');
LogService::delete($conn, $user['id'], 'Deleted customer ID 7');
LogService::statusChange($conn, $user['id'], 'Changed order to completed');
LogService::import($conn, $user['id'], 'Imported 150 records from xlsx');
LogService::backup($conn, $user['id'], 'Created backup file.zip');

// Purge old logs — run from cron
LogService::purgeOlderThan($conn, 180);  // delete logs older than 180 days
```

SQL:

```sql
CREATE TABLE users_logs (
    id       INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id  INT         NULL,
    event    VARCHAR(50) NOT NULL,
    action   TEXT        NOT NULL,
    stamped  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_event   (event),
    INDEX idx_stamped (stamped)
);
```

### LoginService

```php
require_once __DIR__ . '/../../services/LoginService.php';

LoginService::loginAttempt($conn, $userId);   // increment attempt counter
LoginService::resetAttempt($conn, $userId);   // reset to 0 on success
LoginService::isDisabled($user);              // true if account is locked
LoginService::getIp();                        // detect real client IP
LoginService::getDevice();                    // parse User-Agent
LoginService::saveLoginInfo($conn, $userId);  // save IP + device to DB
```

### MailService

```php
require_once __DIR__ . '/../../services/MailService.php';

MailService::send(
    to:      'user@example.com',
    subject: 'Welcome!',
    body:    '<h1>Hello!</h1>',
    toName:  'John Doe',
);
```

### PasswordResetService

```php
require_once __DIR__ . '/../../services/PasswordResetService.php';

// Send 6-digit code to email
PasswordResetService::sendCode($conn, $usernameOrEmail);

// Verify code
PasswordResetService::verifyCode($conn, $usernameOrEmail, $code); // bool

// Reset password
PasswordResetService::resetPassword($conn, $usernameOrEmail, $code, $newPassword);

// Account activation
PasswordResetService::sendActivation($conn, $userId);
PasswordResetService::activateAccount($conn, $token);
PasswordResetService::resendActivation($conn, $usernameOrEmail);
```

---

## CLI Generator

```bash
# Basic
php console.php gen:controller Orders

# Versioned and nested
php console.php gen:controller v1/auth/Login

# With specific methods
php console.php gen:controller v1/orders/Items --methods=list,create,update,delete

# Public endpoint — no auth middleware
php console.php gen:controller v1/auth/Forgot --no-auth

# Public with methods
php console.php gen:controller v1/auth/Forgot --no-auth --methods=forgot,reset,verify

# Overwrite existing
php console.php gen:controller v1/auth/Login --force

# Show help
php console.php list
```

### HTTP Verb Auto-Detection

| Method name                                           | HTTP verb |
| ----------------------------------------------------- | --------- |
| list, index, show, find, get                          | GET       |
| create, store, login, register, forgot, reset, verify | POST      |
| update                                                | PUT       |
| delete, destroy                                       | DELETE    |
| anything else                                         | GET       |

---

## Cron Jobs

```php
<?php
// cron/my_job.php

$config = require __DIR__ . '/../config.php';
$db     = $config['databases'][$config['db_default']];
$conn   = new mysqli($db['host'], $db['user'], $db['password'], $db['name'], $db['port']);

// your job logic here

$conn->close();
```

### Hostinger Setup

1. hPanel > Advanced > Cron Jobs > Custom
2. Schedule: 0 0 \* \* \* (daily midnight)
3. Command:

```bash
php ~/domains/yourdomain.com/public_html/api/cron/my_job.php
```

If the file was created on Windows, fix line endings first:

```bash
dos2unix ~/domains/yourdomain.com/public_html/api/cron/my_job.php
```

---

## Email

Hostinger SMTP settings:

| Setting  | Value                 |
| -------- | --------------------- |
| Host     | smtp.hostinger.com    |
| Port     | 587 (TLS) / 465 (SSL) |
| Username | your Hostinger email  |
| Password | your email password   |

---

## Logging

Only log write operations — never log GET or read requests.

| Action                 | Log? |
| ---------------------- | ---- |
| Login / logout         | Yes  |
| Failed login attempts  | Yes  |
| Create, update, delete | Yes  |
| Status changes         | Yes  |
| Import, backup         | Yes  |
| GET / list / view      | No   |
| Dashboard metrics      | No   |
| Dropdown data          | No   |

Run LogService::purgeOlderThan($conn, 180) weekly via cron to prevent bloat.

---

## Deployment on Hostinger

1. Upload files to public_html/api/ via FTP or File Manager
2. Set PHP to 8.1+ in hPanel > PHP Configuration
3. Fill in config.php — database, JWT secret, mail settings
4. Enable SSL (hPanel > SSL > Let's Encrypt) for production
5. Set debug to false and secure to true in config.php for production
6. Test:

```bash
curl -X POST https://yourdomain.com/api/v1/auth/login.php/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"yourpassword"}'
```

---

## Response Conventions

```php
// Success list
$response($rows, 200);

// Success with message
$response(['success' => true, 'message' => 'Done.'], 200);

// Created
$response(['success' => true, 'message' => 'Created.', 'id' => $newId], 201);

// Validation error
$response(['error' => true, 'message' => $e->getErrors()], 422);

// Not found
$response(['error' => true, 'message' => 'Not found.'], 404);

// Server error
$response(['error' => true, 'message' => $e->getMessage()], 500);
```
