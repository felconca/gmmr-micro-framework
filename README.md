# PHP API Micro-Framework

A lightweight, zero-dependency PHP REST API framework. No Composer, no external libraries — pure PHP 8.1+. Works on any shared hosting, VPS, or local environment.

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
- [Authentication and JWT](#authentication-and-jwt)
- [Middleware](#middleware)
- [Database](#database)
- [Services](#services)
- [CLI Generator](#cli-generator)
- [Cron Jobs](#cron-jobs)
- [Email](#email)
- [Logging](#logging)
- [Deployment](#deployment)
- [Response Conventions](#response-conventions)

---

## Requirements

- PHP 8.1 or higher
- MySQLi extension enabled
- Apache with mod_rewrite enabled (or Nginx with equivalent rewrite rules)
- No Composer required

---

## Installation

1. Copy all files to your server inside your API folder (e.g. `project/api/`)
2. Make sure `.htaccess` is in the same folder as `api.php`
3. Edit `config.php` and fill in your database credentials and JWT secret
4. Done — no build step, no dependency install

```
project/
└── api/
    ├── .htaccess
    ├── index.php
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
├── .htaccess                    — URL rewriting (do not modify)
├── index.php                    — Entry point
├── config.php                   — All configuration
├── console.php                  — CLI code generator
│
├── core/
│   ├── Request.php              — Input parsing and validation
│   ├── Response.php             — JSON output helpers
│   ├── Router.php               — URL to controller dispatch
│   ├── BaseController.php       — Shared DB, config, token access
│   ├── Middleware.php           — Auth, role, method guards
│   ├── TokenService.php         — JWT generate and verify (HS256)
│   └── ValidationException.php
│
├── controllers/
│   ├── sample.php               — example flat controller
│   └── v1/
│       ├── auth/
│       │   └── login.php        → LoginController
│       └── orders/
│           └── orders.php       → OrdersController
│
├── services/
│   ├── SampleService.php        — example service (start here)
│   ├── LoginService.php         — login attempts, IP, device
│   ├── LogService.php           — user activity logging
│   ├── MailService.php          — raw SMTP email sender
│   └── PasswordResetService.php — password reset and activation
│
└── cron/
    └── sample_job.php           — example cron job (start here)
```

---

## Configuration

All settings live in `config.php`. This is the only file you need to edit before deploying.

```php
return [

    'timezone' => 'Asia/Manila',

    'app' => [
        'name'  => 'My API',
        'debug' => false,       // true shows full errors in responses — use only in dev
    ],

    'jwt' => [
        // Generate a secret: php -r "echo bin2hex(random_bytes(32));"
        'secret'        => 'your-long-random-secret-here',
        'access_ttl'    => 900,        // access token lifetime in seconds (15 min)
        'refresh_ttl'   => 604800,     // refresh token lifetime in seconds (7 days)
        'cookie_prefix' => 'app',      // cookies: app_access_token, app_refresh_token
        'secure'        => true,       // false for local HTTP dev, true for production HTTPS
        'same_site'     => 'Strict',   // Strict | Lax | None
    ],

    'db_default' => 'primary',         // key used when $this->db() is called with no argument

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

        // Add more connections as needed:
        // 'secondary' => [ 'driver' => 'mysqli', 'host' => '...', ... ],
        // 'analytics' => [ 'driver' => 'pgsql',  'host' => '...', ... ],
        // 'cache'     => [ 'driver' => 'sqlite', 'path' => __DIR__ . '/storage/db.sqlite' ],
    ],

    'mail' => [
        'driver'     => 'smtp',                    // smtp | mail
        'host'       => 'smtp.yourmailserver.com',
        'port'       => 587,                       // 587 for TLS, 465 for SSL
        'encryption' => 'tls',                    // tls | ssl
        'username'   => 'noreply@yourdomain.com',
        'password'   => 'your_email_password',
        'from_email' => 'noreply@yourdomain.com',
        'from_name'  => 'My App',
    ],

    'cors' => [
        'allowed_origins'   => '*',                // '*' or 'https://yourfrontend.com'
        'allowed_methods'   => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'allowed_headers'   => 'Content-Type, X-Auth-Token, Origin, Authorization',
        'allow_credentials' => true,
    ],
];
```

---

## Routing

The `.htaccess` routes all requests to `api.php`. The Router maps the URL to a controller file and calls the matching method.

### URL Shapes

All three formats are supported and can be mixed freely:

```
/api/{version}/{folder}/{file}.php/{method}?param=value
/api/{version}/{file}.php/{method}?param=value
/api/{file}.php/{method}?param=value
```

You can go as flat or as nested as you want depending on your project size.

### Examples

| HTTP   | URL                                   | File resolved                    | Method called |
| ------ | ------------------------------------- | -------------------------------- | ------------- |
| POST   | /api/v1/auth/login.php/login          | controllers/v1/auth/login.php    | login()       |
| GET    | /api/v1/orders/orders.php/list?page=1 | controllers/v1/orders/orders.php | list()        |
| POST   | /api/v1/auth/login.php/register       | controllers/v1/auth/login.php    | register()    |
| GET    | /api/v1/cylinders.php/list            | controllers/v1/cylinders.php     | list()        |
| POST   | /api/auth.php/login                   | controllers/auth.php             | login()       |
| GET    | /api/users.php/list                   | controllers/users.php            | list()        |
| DELETE | /api/orders.php/delete                | controllers/orders.php           | delete()      |

### Rules

- The version segment (`v1`, `v2`, `version1`, `version2`) is just a folder — name it anything
- `.php` in the URL is optional — `/login.php/login` and `/login/login` both work
- Query string params (`?page=1&limit=50`) always work on any URL shape
- File name maps to class name: `login.php` → `LoginController`, `user_profile.php` → `UserProfileController`
- You can go flat with no version folder: `/api/orders.php/list`
- Or deep with version and subfolder: `/api/v1/finance/invoices.php/create`

### Folder Structure vs URL

```
controllers/
├── users.php              →  /api/users.php/list
├── orders.php             →  /api/orders.php/list
└── v1/
    ├── auth.php           →  /api/v1/auth.php/login
    ├── cylinders.php      →  /api/v1/cylinders.php/list
    └── finance/
        └── invoices.php   →  /api/v1/finance/invoices.php/create
```

---

## Controllers

Every controller extends `BaseController` and lives in the `controllers/` folder.

### Minimal Controller

```php
<?php

require_once __DIR__ . '/../core/BaseController.php';

class UsersController extends BaseController
{
    public function list(Request $request, Response $response): void
    {
        Middleware::method('GET', $request, $response);
        $user = Middleware::auth($request, $response);

        try {
            $conn = $this->db();
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE deleted = 0");
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response($rows, 200);

        } catch (Throwable $e) {
            $response(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
```

### Full Controller with Validation

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
            $createdBy = (int) $user['id'];   // always from token, never from request

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

The path back to `BaseController.php` depends on how deep your controller is:

```php
// controllers/orders.php              (1 level deep)
require_once __DIR__ . '/../core/BaseController.php';

// controllers/v1/orders.php           (2 levels deep)
require_once __DIR__ . '/../../core/BaseController.php';

// controllers/v1/finance/invoices.php (3 levels deep)
require_once __DIR__ . '/../../../core/BaseController.php';
```

The CLI generator calculates this automatically — use it to avoid path mistakes.

---

## Request

### Reading Input

```php
// GET query string: ?page=1&limit=50
$page  = $request->query('page', 1);       // second arg is default
$limit = $request->query('limit', 50);

// POST / JSON body
$name  = $request->input('name');
$email = $request->input('email', '');     // default empty string

// All body data as array
$all = $request->all();

// HTTP method
$method = $request->method();              // 'GET', 'POST', 'PUT', etc.

// Request header
$auth = $request->header('Authorization');

// URL segment param e.g. /orders/123 → param0 = 123
$id = $request->param('param0');
```

### GET vs POST

- `GET` and `DELETE` — data comes from the query string (`$_GET`)
- `POST`, `PUT`, `PATCH` — data comes from the request body (JSON or form-encoded)
- `$request->validate()` and `$request->all()` automatically read the correct source

---

## Response

The response object is callable — invoke it like a function:

```php
// Call with data and status code
$response($data, 200);

// Return an array
$response(['success' => true, 'id' => $newId], 201);

// Shorthand helpers
$response->ok($data);            // 200
$response->created($data);       // 201
$response->noContent();          // 204
$response->badRequest($data);    // 400
$response->unauthorized($data);  // 401
$response->forbidden($data);     // 403
$response->notFound($data);      // 404
$response->error($data);         // 500
```

### Rules

- HTTP status code always goes in the **second argument** — never as a key inside the array
- Never add `status` as a key inside the response array
- `$response()` always exits — no code runs after it

```php
// Correct
$response(['success' => true, 'message' => 'Done'], 200);

// Wrong — status key inside array
$response(['status' => 200, 'success' => true]);
```

---

## Validation

Call `$request->validate()` with a rules array. On failure it throws `ValidationException` which the Router automatically catches and returns a 422 response. On success it returns all input merged with validated fields.

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
    'notes'     => '',                            // optional, no rules — still returned
]);
```

### All Available Rules

| Rule            | Description                                      |
| --------------- | ------------------------------------------------ |
| required        | Must be present and not empty                    |
| string          | Must be a string                                 |
| numeric         | Must be a number                                 |
| integer         | Must be a whole number                           |
| float           | Must be a decimal number                         |
| boolean         | Must be true / false / 1 / 0                     |
| email           | Valid email format                               |
| url             | Valid URL format                                 |
| alpha           | Letters only                                     |
| alpha_num       | Letters and numbers only                         |
| alpha_dash      | Letters, numbers, dashes, underscores            |
| min:{n}         | String length / numeric value / array count >= n |
| max:{n}         | String length / numeric value / array count <= n |
| in:{a},{b}      | Value must be one of the listed options          |
| not_in:{a},{b}  | Value must NOT be one of the listed options      |
| date            | Valid date (Y-m-d)                               |
| datetime        | Valid datetime (Y-m-d H:i:s)                     |
| time            | Valid time (H:i or H:i:s)                        |
| before:{date}   | Must be before the given date                    |
| after:{date}    | Must be after the given date                     |
| regex:{pattern} | Must match the given regex pattern               |
| array           | Must be an array                                 |

### Optional Fields

Fields without `required` are fully skipped when not present in the payload — no error thrown:

```php
$data = $request->validate([
    'id'     => 'required|numeric|min:1',  // always validated
    'status' => 'required',                // always validated
    'notes'  => 'string',                  // only validated IF sent
    'date'   => 'date',                    // only validated IF sent
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

### Validation Error Format (422)

```json
{
  "error": true,
  "message": {
    "email": ["email is required.", "email must be a valid email address."],
    "age": ["age must be at least 1."]
  }
}
```

---

## Authentication and JWT

The framework uses HS256 JWT tokens — no external library needed.

### Issue Tokens on Login

```php
$tokens = $this->tokens()->issue([
    'id'       => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
]);

// Tokens are written to HttpOnly cookies automatically.
// Also returned in the response body for mobile clients.
$response(['message' => 'Login successful', 'tokens' => $tokens], 200);
```

### Protect an Endpoint

```php
$user = Middleware::auth($request, $response);
// Returns decoded token payload
// $user['id'], $user['username'], $user['role']
```

### Token via Authorization Header (Mobile / React Native)

Send the token in every request header:

```
Authorization: Bearer <access_token>
```

The framework checks the `Authorization` header first, then falls back to the cookie. Browser and mobile clients both work from the same API without any changes.

The `.htaccess` already includes the line that passes the header through to PHP:

```apache
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
```

### Refresh and Revoke

```php
// Issue a new token pair using the refresh token
$tokens = $this->tokens()->refresh();

// Clear both cookies (logout)
$this->tokens()->revoke();

// Verify the access token without issuing new ones
$payload = $this->tokens()->verifyFromCookie();
```

---

## Middleware

All guards are static — call them at the top of any controller method.

```php
// Enforce HTTP method — sends 405 if wrong
Middleware::method('POST', $request, $response);

// Require valid token — sends 401 if missing or invalid
// Returns the decoded token payload
$user = Middleware::auth($request, $response);

// Require a specific role — sends 403 if role does not match
$user = Middleware::role(['admin', 'superadmin'], $request, $response);

// Block logged-in users (e.g. login / register pages)
// Sends 403 if a valid token is already present
Middleware::guest($request, $response);
```

---

## Database

### Connect

```php
$conn = $this->db();                       // default connection (db_default in config)
$conn = $this->db('secondary');            // named connection from config
$conn = $this->db($request->input('db')); // dynamic — key from request
```

Multiple connections stay open at the same time. Opening the same key twice returns the same instance (pooled).

### Prepared Statements

Always use prepared statements — never interpolate user input directly into SQL.

```php
// SELECT one row
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND deleted = 0 LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// SELECT multiple rows
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

// DELETE
$stmt = $conn->prepare("DELETE FROM logs WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();
```

### bind_param Type Characters

| Char | PHP type       |
| ---- | -------------- |
| i    | integer        |
| s    | string         |
| d    | double / float |
| b    | blob           |

### Dynamic Filters (WHERE builder pattern)

```php
$where     = ['deleted = 0'];
$params    = [];
$types_str = '';

if ($status !== 'all') {
    $where[]    = 'status = ?';
    $params[]   = $status;
    $types_str .= 's';
}
if ($customerId) {
    $where[]    = 'customer_id = ?';
    $params[]   = $customerId;
    $types_str .= 'i';
}

$whereClause = implode(' AND ', $where);

// Add LIMIT and OFFSET at the end
$params[]   = $limit;
$params[]   = $offset;
$types_str .= 'ii';

$stmt = $conn->prepare("SELECT * FROM orders WHERE {$whereClause} ORDER BY id ASC LIMIT ? OFFSET ?");
if (!empty($params)) {
    $stmt->bind_param($types_str, ...$params);
}
$stmt->execute();
```

### Supported Drivers

| Driver     | Config value |
| ---------- | ------------ |
| MySQL      | mysqli       |
| PostgreSQL | pgsql        |
| SQL Server | sqlsrv       |
| SQLite     | sqlite       |

---

## Services

Services are static classes that handle business logic. They have no knowledge of HTTP — no Request, no Response, no echo. Controllers call services to keep themselves thin.

See `services/SampleService.php` for a fully documented example with common patterns.

### How to Use a Service

```php
// At the top of your controller file
require_once __DIR__ . '/../../services/SampleService.php';

// Inside any controller method
$record = SampleService::findById($conn, $id);
$exists = SampleService::exists($conn, 'email', $data['email']);
$refNo  = SampleService::generateRefNo($conn, 'ORD', 'orders');
SampleService::changeStatus($conn, $id, 'active');
SampleService::softDelete($conn, $id);
$result = SampleService::batchUpdateStatus($conn, $ids, 'inactive');
```

### LogService

```php
require_once __DIR__ . '/../../services/LogService.php';

// Only log write operations — never log GET / reads
LogService::login($conn, $user['id'], $ip, $device);
LogService::logout($conn, $user['id']);
LogService::create($conn, $user['id'], 'Created delivery DLV-20260501-001');
LogService::update($conn, $user['id'], 'Updated cylinder ID 42');
LogService::delete($conn, $user['id'], 'Deleted customer ID 7');
LogService::statusChange($conn, $user['id'], 'Changed order to completed');
LogService::import($conn, $user['id'], 'Imported 150 records from xlsx');
LogService::backup($conn, $user['id'], 'Created backup file.zip');

// Call from a cron job to prevent table bloat
LogService::purgeOlderThan($conn, 180);  // delete logs older than 180 days
```

SQL to create the log table:

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

LoginService::loginAttempt($conn, $userId);   // increment attempt counter (locks at 5)
LoginService::resetAttempt($conn, $userId);   // reset to 0 on successful login
LoginService::isDisabled($user);              // true if status=disabled or attempts >= 5
LoginService::getIp();                        // detect real client IP behind proxies
LoginService::getDevice();                    // parse User-Agent into readable string
LoginService::saveLoginInfo($conn, $userId);  // save IP + device to users table
```

### MailService

```php
require_once __DIR__ . '/../../services/MailService.php';

MailService::send(
    to:      'user@example.com',
    subject: 'Welcome to the app!',
    body:    '<h1>Hello!</h1><p>Your account is ready.</p>',
    toName:  'John Doe',               // optional
);
```

### PasswordResetService

```php
require_once __DIR__ . '/../../services/PasswordResetService.php';

// Forgot password — sends a 6-digit code to the user's email
PasswordResetService::sendCode($conn, $usernameOrEmail);

// Verify the code before showing the new password form
$valid = PasswordResetService::verifyCode($conn, $usernameOrEmail, $code);  // returns bool

// Reset the password using the verified code
PasswordResetService::resetPassword($conn, $usernameOrEmail, $code, $newPassword);

// Account activation — call after creating a new user
PasswordResetService::sendActivation($conn, $userId);
PasswordResetService::activateAccount($conn, $token);
PasswordResetService::resendActivation($conn, $usernameOrEmail);
```

---

## CLI Generator

Generate controller files from the terminal — no manual file creation or path calculation needed.

```bash
# Flat controller — controllers/orders.php
php console.php gen:controller Orders

# Versioned — controllers/v1/auth/login.php
php console.php gen:controller v1/auth/Login

# Versioned, no subfolder — controllers/v1/cylinders.php
php console.php gen:controller v1/Cylinders

# With specific methods
php console.php gen:controller v1/orders/Items --methods=list,create,update,delete

# Public endpoint — no Middleware::auth generated
php console.php gen:controller v1/auth/Forgot --no-auth

# Public with specific methods
php console.php gen:controller v1/auth/Forgot --no-auth --methods=forgot,reset,verify

# Overwrite an existing file
php console.php gen:controller v1/auth/Login --force

# Show all commands and options
php console.php list
```

### Output

```
✔  Controller created: controllers/v1/orders/items.php
   Class   : ItemsController
   Methods : list, create, update, delete
   Auth    : Yes (Middleware::auth)

   URL     : /api/v1/orders/items.php/{method}
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

Cron scripts are standalone PHP files in the `cron/` folder. They connect directly to the database using the config and run scheduled tasks.

See `cron/sample_job.php` for a fully documented starting point.

### Basic Structure

```php
<?php
// cron/my_job.php

$config = require __DIR__ . '/../config.php';
$db     = $config['databases'][$config['db_default']];
$conn   = new mysqli($db['host'], $db['user'], $db['password'], $db['name'], $db['port']);

if ($conn->connect_error) {
    echo "[ERROR] " . $conn->connect_error . "\n";
    exit(1);
}

// your job logic here
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Job completed.\n";

$conn->close();
```

### Running Manually

```bash
php /path/to/api/cron/my_job.php
```

### Schedule Format

```
* * * * *
| | | | |
| | | | └── Day of week (0-7, 0 and 7 = Sunday)
| | | └──── Month (1-12)
| | └────── Day of month (1-31)
| └──────── Hour (0-23)
└────────── Minute (0-59)

0 0 * * *     daily at midnight
0 8 * * *     daily at 8am
0 0 * * 0     every Sunday at midnight
0 0 1 * *     first day of every month
*/30 * * * *  every 30 minutes
```

### Scheduling on a Server

Most control panels (cPanel, Plesk, DirectAdmin, custom VPS) have a Cron Jobs section. The command to run a PHP script is:

```bash
php /full/path/to/api/cron/my_job.php
```

To save output to a log file:

```bash
php /full/path/to/api/cron/my_job.php >> /path/to/logs/my_job.log 2>&1
```

**Note:** If you created the cron file on Windows and uploaded it, fix line endings before running:

```bash
dos2unix /path/to/api/cron/my_job.php
# or
sed -i 's/\r//' /path/to/api/cron/my_job.php
```

---

## Email

The framework sends email via raw PHP SMTP sockets — no Composer, no PHPMailer needed.

Configure your SMTP settings in `config.php` under the `mail` key:

```php
'mail' => [
    'driver'     => 'smtp',
    'host'       => 'smtp.yourmailserver.com',
    'port'       => 587,                       // 587 for TLS, 465 for SSL
    'encryption' => 'tls',
    'username'   => 'noreply@yourdomain.com',
    'password'   => 'your_email_password',
    'from_email' => 'noreply@yourdomain.com',
    'from_name'  => 'My App',
],
```

Common SMTP providers:

| Provider | Host                | Port |
| -------- | ------------------- | ---- |
| Gmail    | smtp.gmail.com      | 587  |
| Outlook  | smtp.office365.com  | 587  |
| SendGrid | smtp.sendgrid.net   | 587  |
| Mailgun  | smtp.mailgun.org    | 587  |
| Any host | smtp.yourdomain.com | 587  |

---

## Logging

Only write operations should be logged — never log GET or read requests.

| Action                 | Log? |
| ---------------------- | ---- |
| Login / logout         | Yes  |
| Failed login attempts  | Yes  |
| Create, update, delete | Yes  |
| Status changes         | Yes  |
| Import, backup         | Yes  |
| GET / list / view      | No   |
| Dashboard metrics      | No   |
| Dropdown fetches       | No   |

Call `LogService::purgeOlderThan($conn, 180)` from a weekly cron job to delete logs older than 6 months and prevent table bloat.

---

## Deployment

### Steps

1. Upload all files to your server's API directory
2. Set PHP to 8.1 or higher in your server control panel
3. Edit `config.php` — set database credentials, JWT secret, mail settings
4. Enable HTTPS / SSL on your domain (required for secure cookies in production)
5. Set `'debug' => false` and `'secure' => true` in `config.php` for production
6. Test the API:

```bash
curl -X POST https://yourdomain.com/api/v1/auth/login.php/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"yourpassword"}'
```

### Apache

The `.htaccess` is already configured — just make sure `mod_rewrite` is enabled on your server.

### Nginx

If using Nginx instead of Apache, add this rewrite rule to your server block:

```nginx
location /api/ {
    try_files $uri $uri/ /api/api.php?x=$uri&$args;
}
```

### Environment Variables (optional)

If you prefer not to put credentials in `config.php`, you can use environment variables instead:

```php
// In config.php
'databases' => [
    'primary' => [
        'driver'   => 'mysqli',
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'name'     => getenv('DB_NAME') ?: 'my_database',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'port'     => 3306,
        'charset'  => 'utf8mb4',
    ],
],
'jwt' => [
    'secret' => getenv('JWT_SECRET') ?: 'fallback-dev-secret',
    // ...
],
```

---

## Response Conventions

Consistent response shape across all endpoints:

```php
// Success — list / data
$response($rows, 200);

// Success — with message
$response(['success' => true, 'message' => 'Done.'], 200);

// Success — created
$response(['success' => true, 'message' => 'Created.', 'id' => $newId], 201);

// Success — with pagination
$response(['data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit], 200);

// Validation error (422)
$response(['error' => true, 'message' => $e->getErrors()], 422);

// Not found (404)
$response(['error' => true, 'message' => 'Record not found.'], 404);

// Conflict / duplicate (409)
$response(['error' => true, 'message' => 'Email already exists.'], 409);

// Server error (500)
$response(['error' => true, 'message' => $e->getMessage()], 500);
```
