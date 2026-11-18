<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use LogicException;

/**
 * Validasi input server-side.
 *
 *   $v = new Validator($_POST, [
 *       'email'    => 'required|email|max:150|unique:users,email',
 *       'password' => 'required|password|confirmed',
 *   ], ['email' => 'Email']);
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $data = $v->validated();
 *
 * Field yang tidak 'required' dan kosong akan dilewati (opsional).
 */
final class Validator
{
    private array $data = [];
    private array $errors = [];

    public function __construct(array $input, private array $rules, private array $labels = [])
    {
        foreach (array_keys($rules) as $field) {
            $value = $input[$field] ?? null;
            // Password tidak dipangkas spasinya; field lain dipangkas.
            if (is_string($value) && !str_contains($field, 'password')) {
                $value = trim($value);
            }
            $this->data[$field] = $value;
        }
        // Simpan juga field *_confirmation untuk aturan 'confirmed'.
        foreach ($input as $key => $value) {
            if (is_string($key) && str_ends_with($key, '_confirmation')) {
                $this->data[$key] = $value;
            }
        }
        $this->run();
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** Pesan error pertama per field: ['email' => 'Email wajib diisi.'] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Data yang sudah dipangkas, hanya field yang ada di daftar aturan. */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            $value = $this->data[$field];
            $result[$field] = ($value === '' ? null : $value);
        }
        return $result;
    }

    /** Tambah error manual (mis. hasil pengecekan bisnis di controller). */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field];
            $isEmpty = $value === null || $value === '' || $value === [];

            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, "{$this->label($field)} wajib diisi.");
                }
                continue;
            }
            if (!is_string($value) && !in_array('array', $rules, true)) {
                $this->addError($field, "{$this->label($field)} tidak valid.");
                continue;
            }

            $numeric = in_array('numeric', $rules, true) || in_array('integer', $rules, true);
            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
                $message = $this->check($field, $value, $name, $param, $numeric);
                if ($message !== null) {
                    $this->addError($field, $message);
                    break;
                }
            }
        }
    }

    private function check(string $field, mixed $value, string $rule, string $param, bool $numeric): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'required', 'array' => null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$label} harus berupa alamat email yang valid.",
            'numeric' => is_numeric($value) ? null : "{$label} harus berupa angka.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "{$label} harus berupa bilangan bulat.",
            'min' => $numeric
                ? ((float) $value >= (float) $param ? null : "{$label} minimal {$param}.")
                : (mb_strlen($value) >= (int) $param ? null : "{$label} minimal {$param} karakter."),
            'max' => $numeric
                ? ((float) $value <= (float) $param ? null : "{$label} maksimal {$param}.")
                : (mb_strlen($value) <= (int) $param ? null : "{$label} maksimal {$param} karakter."),
            'in' => in_array($value, explode(',', $param), true) ? null : "{$label} tidak valid.",
            'date' => self::isDate($value) ? null : "{$label} harus berupa tanggal yang valid.",
            'after_or_equal' => $this->afterOrEqual($value, $param) ? null : "{$label} tidak boleh sebelum {$this->paramLabel($param)}.",
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value ? null : "Konfirmasi {$label} tidak sama.",
            'password' => (mb_strlen($value) >= 8 && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value))
                ? null : "{$label} minimal 8 karakter dan mengandung huruf serta angka.",
            'phone' => preg_match('/^(\+62|62|0)8\d{7,12}$/', preg_replace('/[\s.-]/', '', $value)) ? null
                : "{$label} harus nomor HP Indonesia yang valid (contoh: 081234567890).",
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? null : "{$label} hanya boleh huruf kecil, angka, dan tanda hubung.",
            'unique' => $this->isUnique($value, $param) ? null : "{$label} sudah digunakan.",
            'exists' => $this->exists($value, $param) ? null : "{$label} tidak ditemukan.",
            default => throw new LogicException("Aturan validasi tidak dikenal: {$rule}"),
        };
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function paramLabel(string $param): string
    {
        return $param === 'today' ? 'hari ini' : $this->label($param);
    }

    public static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function afterOrEqual(string $value, string $param): bool
    {
        $other = $param === 'today' ? date('Y-m-d') : (string) ($this->data[$param] ?? '');
        if (!self::isDate($value) || !self::isDate($other)) {
            return true; // format tanggal sudah dilaporkan oleh aturan 'date'
        }
        return $value >= $other;
    }

    /** unique:tabel,kolom[,id_yang_dikecualikan] */
    private function isUnique(string $value, string $param): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $param), 3, null);
        self::assertIdentifier($table, $column);
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $ignoreId;
        }
        return (int) Database::value($sql, $params) === 0;
    }

    /** exists:tabel,kolom */
    private function exists(string $value, string $param): bool
    {
        [$table, $column] = array_pad(explode(',', $param), 2, 'id');
        self::assertIdentifier($table, $column);
        return (int) Database::value("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value]) > 0;
    }

    /** Nama tabel/kolom berasal dari kode, tapi tetap dicek agar tidak bisa disisipi SQL. */
    private static function assertIdentifier(?string ...$names): void
    {
        foreach ($names as $name) {
            if ($name === null || !preg_match('/^[a-z_]+$/', $name)) {
                throw new LogicException('Nama tabel/kolom tidak valid pada aturan validasi.');
            }
        }
    }
}
