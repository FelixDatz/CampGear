<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use mysqli_stmt;
use Throwable;

/**
 * Pembungkus koneksi MySQLi.
 *
 * Semua query yang menerima data dari luar WAJIB lewat method di sini,
 * yang selalu memakai prepared statement (bind_param) untuk mencegah SQL injection.
 */
final class Database
{
    private static ?mysqli $connection = null;

    public static function connection(): mysqli
    {
        if (self::$connection === null) {
            // Error MySQLi dilempar sebagai exception, bukan warning diam-diam.
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $conn = new mysqli(
                (string) config('db.host'),
                (string) config('db.user'),
                (string) config('db.pass'),
                (string) config('db.name'),
                (int) config('db.port', 3306),
            );
            $conn->set_charset('utf8mb4');
            // Mode strict: data tidak valid ditolak, bukan dipotong diam-diam.
            $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $conn->query("SET time_zone = '" . date('P') . "'");

            self::$connection = $conn;
        }
        return self::$connection;
    }

    /** Menjalankan query dan mengembalikan semua baris sebagai array asosiatif. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::run($sql, $params);
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /** Mengembalikan baris pertama atau null. */
    public static function first(string $sql, array $params = []): ?array
    {
        return self::select($sql, $params)[0] ?? null;
    }

    /** Mengembalikan nilai kolom pertama dari baris pertama (untuk COUNT, SUM, dll). */
    public static function value(string $sql, array $params = []): mixed
    {
        $row = self::first($sql, $params);
        return $row === null ? null : reset($row);
    }

    /** INSERT, mengembalikan id baris baru. */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::run($sql, $params);
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /** UPDATE/DELETE, mengembalikan jumlah baris yang terpengaruh. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::run($sql, $params);
        $affected = (int) $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Menjalankan beberapa query sebagai satu transaksi.
     * Bila terjadi exception, semua perubahan dibatalkan (rollback).
     */
    public static function transaction(callable $callback): mixed
    {
        $conn = self::connection();
        $conn->begin_transaction();
        try {
            $result = $callback();
            $conn->commit();
            return $result;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    private static function run(string $sql, array $params): mysqli_stmt
    {
        $stmt = self::connection()->prepare($sql);

        if ($params !== []) {
            $params = array_values($params);
            $types = '';
            foreach ($params as $i => $param) {
                $types .= match (true) {
                    is_int($param)   => 'i',
                    is_float($param) => 'd',
                    default          => 's',
                };
                if (is_bool($param)) {
                    $params[$i] = (int) $param;
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }
}
