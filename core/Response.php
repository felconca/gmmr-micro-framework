<?php

class Response
{
    private static array $statusMessages = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    /**
     * Make the Response callable so controllers can write:
     *
     *   return $response($data);
     *   return $response($data, 201);
     *   return $response(['error' => '...'], 400);
     */
    public function __invoke(mixed $data, int $status = 200): never
    {
        $this->send($data, $status);
    }

    // ── Shorthand helpers ────────────────────────────────────────────────────
    public function ok(mixed $data): never            { $this->send($data, 200); }
    public function created(mixed $data): never       { $this->send($data, 201); }
    public function noContent(): never                { $this->send(null, 204); }
    public function badRequest(mixed $data): never    { $this->send($data, 400); }
    public function unauthorized(mixed $data): never  { $this->send($data, 401); }
    public function forbidden(mixed $data): never     { $this->send($data, 403); }
    public function notFound(mixed $data): never      { $this->send($data, 404); }
    public function error(mixed $data): never         { $this->send($data, 500); }

    // ── Core sender ──────────────────────────────────────────────────────────
    public function send(mixed $data, int $status = 200): never
    {
        $message = self::$statusMessages[$status] ?? 'Unknown';
        header("HTTP/1.1 {$status} {$message}");
        header('Content-Type: application/json; charset=UTF-8');

        if ($status === 204 || $data === null) {
            exit;
        }

        echo is_array($data) || is_object($data)
            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $data;

        exit;
    }
}
