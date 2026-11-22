<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\BusinessRuleException;

/**
 * Upload gambar yang aman.
 *
 *  - Ukuran dibatasi 2 MB.
 *  - Jenis file ditentukan dari ISI file (finfo + getimagesize), bukan dari nama/ekstensi
 *    kiriman pengguna, sehingga file .php yang diganti namanya menjadi .jpg tetap ditolak.
 *  - Nama file diganti acak; nama asli tidak pernah dipakai di server.
 *  - Disimpan di public/uploads/<folder>/ yang tidak bisa mengeksekusi skrip (.htaccess).
 */
final class Upload
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const FOLDERS = ['equipment', 'packages', 'articles'];
    private const PRIVATE_FOLDERS = ['payments'];

    /**
     * Simpan gambar dari field form. Mengembalikan path relatif (mis. 'equipment/ab12...jpg'),
     * atau null bila tidak ada file yang dipilih.
     *
     * @throws BusinessRuleException bila file tidak valid
     */
    public static function image(string $field, string $folder): ?string
    {
        if (!in_array($folder, self::FOLDERS, true)) {
            throw new \LogicException("Folder upload tidak dikenal: {$folder}");
        }

        $validated = self::validateImageUpload($field);
        if ($validated === null) {
            return null;
        }

        $directory = BASE_PATH . '/public/uploads/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Folder upload tidak dapat dibuat.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        if (!move_uploaded_file($validated['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan gambar.');
        }
        @chmod($directory . '/' . $name, 0644);

        return $folder . '/' . $name;
    }

    /**
     * Simpan gambar secara privat di storage/<folder>/ (di luar root publik web).
     * Mengembalikan path relatif (mis. 'payments/ab12...jpg'), atau null bila tidak ada file.
     *
     * @throws BusinessRuleException bila file tidak valid
     */
    public static function privateImage(string $field, string $folder): ?string
    {
        if (!in_array($folder, self::PRIVATE_FOLDERS, true)) {
            throw new \LogicException("Folder upload privat tidak dikenal: {$folder}");
        }

        $validated = self::validateImageUpload($field);
        if ($validated === null) {
            return null;
        }

        $directory = BASE_PATH . '/storage/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Folder upload privat tidak dapat dibuat.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        if (!move_uploaded_file($validated['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan gambar bukti.');
        }
        @chmod($directory . '/' . $name, 0644);

        return $folder . '/' . $name;
    }

    /**
     * Memvalidasi file upload gambar (ukuran, tipe MIME dari isi file, dimensi).
     *
     * @return array{tmp_name: string, extension: string}|null
     * @throws BusinessRuleException bila file tidak valid
     */
    private static function validateImageUpload(string $field): ?array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new BusinessRuleException(match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar maksimal 2 MB.',
                UPLOAD_ERR_PARTIAL => 'Gambar hanya terunggah sebagian. Coba lagi.',
                default => 'Gambar gagal diunggah.',
            });
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new BusinessRuleException('Ukuran gambar maksimal 2 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new BusinessRuleException('File unggahan tidak valid.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = self::TYPES[$mime] ?? null;
        $size = @getimagesize($file['tmp_name']);
        if ($extension === null || $size === false || $size[0] < 1 || $size[1] < 1) {
            throw new BusinessRuleException('File harus berupa gambar JPG, PNG, atau WEBP.');
        }

        return ['tmp_name' => $file['tmp_name'], 'extension' => $extension];
    }

    /** Hapus file unggahan lama. Hanya path dengan format buatan Upload::image() yang dihapus. */
    public static function delete(?string $path): void
    {
        if ($path === null || !preg_match('#^(equipment|packages|articles)/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
            return;
        }
        $file = BASE_PATH . '/public/uploads/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Hapus file privat lama. Hanya path dengan format buatan Upload::privateImage() yang dihapus. */
    public static function deletePrivate(?string $path): void
    {
        if ($path === null || !preg_match('#^payments/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
            return;
        }
        $file = BASE_PATH . '/storage/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Mengembalikan path absolut file privat yang aman untuk dibaca/distream,
     * atau null bila path tidak valid atau file tidak ada di disk privat.
     */
    public static function privatePath(?string $path): ?string
    {
        if ($path === null || !preg_match('#^payments/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
            return null;
        }
        $file = BASE_PATH . '/storage/' . $path;
        return is_file($file) ? $file : null;
    }
}
