<?php

/**
 * SampleService.php
 *
 * This is a sample service file to demonstrate how services work.
 *
 * WHAT IS A SERVICE?
 * ------------------
 * A service is a standalone class that handles a specific piece of
 * business logic. It is NOT a controller — it has no knowledge of
 * HTTP requests or responses. It just does the work and returns a result.
 *
 * WHY USE SERVICES?
 * -----------------
 * Instead of putting all your logic inside controllers (which makes them
 * bloated and hard to maintain), you extract reusable logic into services.
 * Multiple controllers can then call the same service.
 *
 * HOW TO USE IN A CONTROLLER:
 * ---------------------------
 *   require_once __DIR__ . '/../../services/SampleService.php';
 *
 *   // Then call it inside any method:
 *   $result = SampleService::doSomething($conn, $someId);
 *
 * RULES:
 * ------
 * - All methods are static — no need to instantiate
 * - Always receive mysqli $conn as the first parameter
 * - Never use Request or Response inside a service
 * - Never echo or die inside a service — throw exceptions instead
 * - Wrap risky operations in try/catch or let the caller handle it
 */
class SampleService
{
    // ── Constants ─────────────────────────────────────────────────────────────
    // Define any fixed values your service uses here.
    // Controllers can reference these too e.g. SampleService::STATUS_ACTIVE

    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING  = 'pending';

    // ── Example 1: Simple fetch ───────────────────────────────────────────────

    /**
     * Get a single record by ID.
     *
     * Usage:
     *   $record = SampleService::findById($conn, 5);
     *   if (!$record) { ... }
     *
     * @param  mysqli $conn
     * @param  int    $id
     * @return array|null
     */
    public static function findById(mysqli $conn, int $id): ?array
    {
        $stmt = $conn->prepare("
            SELECT * FROM sample_table WHERE id = ? AND deleted = 0 LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    // ── Example 2: Check existence ────────────────────────────────────────────

    /**
     * Check if a record with a given value exists.
     * Useful for duplicate checking before insert.
     *
     * Usage:
     *   if (SampleService::exists($conn, 'email', 'john@example.com')) {
     *       // handle duplicate
     *   }
     *
     * @param  mysqli $conn
     * @param  string $column   Column to check e.g. 'email', 'barcode'
     * @param  string $value    Value to look for
     * @param  int    $excludeId  Pass a record ID to exclude (for update checks)
     * @return bool
     */
    public static function exists(mysqli $conn, string $column, string $value, int $excludeId = 0): bool
    {
        $sql = "SELECT id FROM sample_table WHERE `{$column}` = ? AND deleted = 0";

        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $stmt = $conn->prepare($sql . " LIMIT 1");
            $stmt->bind_param('si', $value, $excludeId);
        } else {
            $stmt = $conn->prepare($sql . " LIMIT 1");
            $stmt->bind_param('s', $value);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (bool) $row;
    }

    // ── Example 3: Update a counter/total ─────────────────────────────────────

    /**
     * Recalculate and update a running total column.
     * This pattern is used whenever related records change (insert/delete).
     *
     * Usage:
     *   SampleService::recalculateTotal($conn, $parentId);
     *
     * @param  mysqli $conn
     * @param  int    $parentId
     * @return int    The new total
     */
    public static function recalculateTotal(mysqli $conn, int $parentId): int
    {
        // Count related child records
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM child_table
            WHERE parent_id = ? AND deleted = 0
        ");
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $row   = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (int)($row['total'] ?? 0);

        // Write the total back to the parent record
        $stmt = $conn->prepare("
            UPDATE parent_table SET items_total = ? WHERE id = ?
        ");
        $stmt->bind_param('ii', $total, $parentId);
        $stmt->execute();
        $stmt->close();

        return $total;
    }

    // ── Example 4: Status change ──────────────────────────────────────────────

    /**
     * Change the status of a record.
     * Throws an exception if the status is not allowed.
     *
     * Usage:
     *   SampleService::changeStatus($conn, $recordId, 'active');
     *
     * @param  mysqli $conn
     * @param  int    $id
     * @param  string $status
     * @throws RuntimeException if status is invalid
     */
    public static function changeStatus(mysqli $conn, int $id, string $status): void
    {
        $allowed = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_PENDING];

        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException("Invalid status [{$status}]. Allowed: " . implode(', ', $allowed));
        }

        $stmt = $conn->prepare("UPDATE sample_table SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
    }

    // ── Example 5: Generate a unique reference number ─────────────────────────

    /**
     * Generate a unique reference number.
     * Format: PREFIX-YYYYMMDD-NNN  e.g. ORD-20260514-001
     *
     * Usage:
     *   $refNo = SampleService::generateRefNo($conn, 'ORD', 'orders');
     *
     * @param  mysqli  $conn
     * @param  string  $prefix   e.g. 'ORD', 'DLV', 'MTN'
     * @param  string  $table    Table to count existing records from
     * @param  int     $padLength  How many digits to pad to (default 3)
     * @return string
     */
    public static function generateRefNo(mysqli $conn, string $prefix, string $table, int $padLength = 3): string
    {
        $datePart = date('Ymd');

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE deleted = 0");
        $stmt->execute();
        $row   = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count  = (int)($row['cnt'] ?? 0);
        $serial = str_pad((string)($count + 1), $padLength, '0', STR_PAD_LEFT);

        return "{$prefix}-{$datePart}-{$serial}";
    }

    // ── Example 6: Soft delete with side effects ──────────────────────────────

    /**
     * Soft delete a record and handle any side effects.
     * Returns true if deleted, false if not found.
     *
     * Usage:
     *   $deleted = SampleService::softDelete($conn, $id);
     *
     * @param  mysqli $conn
     * @param  int    $id
     * @return bool
     */
    public static function softDelete(mysqli $conn, int $id): bool
    {
        // Get the record first so we can use its data for side effects
        $record = self::findById($conn, $id);
        if (!$record) return false;

        // Soft delete
        $stmt = $conn->prepare("UPDATE sample_table SET deleted = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected && isset($record['parent_id'])) {
            // Recalculate parent total after deletion
            self::recalculateTotal($conn, (int)$record['parent_id']);
        }

        return (bool) $affected;
    }

    // ── Example 7: Batch operation ────────────────────────────────────────────

    /**
     * Process multiple records in a loop.
     * Returns a summary of what was processed.
     *
     * Usage:
     *   $result = SampleService::batchUpdateStatus($conn, [1, 2, 3], 'active');
     *   echo $result['updated'] . ' records updated';
     *
     * @param  mysqli   $conn
     * @param  int[]    $ids
     * @param  string   $status
     * @return array    ['updated' => int, 'failed' => int]
     */
    public static function batchUpdateStatus(mysqli $conn, array $ids, string $status): array
    {
        $updated = 0;
        $failed  = 0;

        foreach ($ids as $id) {
            try {
                self::changeStatus($conn, (int)$id, $status);
                $updated++;
            } catch (RuntimeException) {
                $failed++;
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }
}
