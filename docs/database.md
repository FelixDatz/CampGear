# Rancangan Database CampGear

Database relasional MySQL/MariaDB (InnoDB, `utf8mb4_unicode_ci`) dengan 16 tabel. Integritas dijaga oleh foreign key, `UNIQUE`, dan `CHECK` constraint. Seluruh query aplikasi memakai prepared statement MySQLi.

## File SQL

| File | Isi | Kapan dijalankan |
|---|---|---|
| `database/schema.sql` | Struktur 16 tabel. **Menghapus tabel lama** sebelum membuat ulang | Instalasi baru |
| `database/seed.sql` | 3 akun demo, 6 kategori, 15 alat, 3 paket | Instalasi baru, setelah schema |
| `database/seed_articles.sql` | 4 artikel informatif; idempoten (`WHERE NOT EXISTS`) | Instalasi baru, atau ditambahkan ke database yang sudah ada |
| `database/demo_data.sql` | ±195 booking contoh (6 bulan, semua status) dengan tanggal relatif `CURDATE()` | Opsional, hanya pada database baru (untuk grafik & demo) |
| `database/setup.php` | Menjalankan file di atas lewat CLI | Pengembangan lokal |
| `database/tools/generate-demo.php` | Pembuat `demo_data.sql` | Bila data demo perlu dibuat ulang |

## Diagram Relasi (ERD)

```mermaid
erDiagram
    users ||--o{ bookings : "memesan (opsional)"
    users ||--o{ remember_tokens : memiliki
    users ||--o{ articles : menulis
    users ||--o{ audit_logs : melakukan
    categories ||--o{ equipment : mengelompokkan
    packages ||--|{ package_items : berisi
    equipment ||--o{ package_items : "menjadi isi"
    bookings ||--|{ booking_items : berisi
    equipment ||--o{ booking_items : disewa
    packages ||--o{ booking_items : "asal paket"
    bookings ||--o{ payments : dibayar
    bookings ||--o{ cancellation_requests : diajukan
    bookings ||--o{ handover_checklists : "serah terima"
    handover_checklists ||--|{ handover_items : berisi
    booking_items ||--o{ handover_items : diperiksa
    bookings ||--o{ damage_reports : "rusak/hilang"
    booking_items ||--o{ damage_reports : terkait

    users {
        int id PK
        varchar email UK
        varchar password_hash
        enum role "user|staff|admin"
        tinyint is_active
    }
    categories {
        int id PK
        varchar slug UK
        varchar icon
    }
    equipment {
        int id PK
        int category_id FK
        varchar slug UK
        int stock_total
        decimal daily_rate
        tinyint is_active
    }
    packages {
        int id PK
        varchar slug UK
        decimal discount_percent
    }
    package_items {
        int package_id PK,FK
        int equipment_id PK,FK
        int quantity
    }
    bookings {
        int id PK
        varchar code UK
        int user_id FK "NULL = tamu"
        date start_date
        date end_date
        decimal grand_total
        enum status
        decimal deposit_amount
        enum deposit_status
        datetime payment_due_at
    }
    booking_items {
        int id PK
        int booking_id FK
        int equipment_id FK
        int package_id FK
        int quantity
        decimal daily_rate "snapshot"
        decimal discount_amount
    }
    payments {
        int id PK
        int booking_id FK
        enum purpose "rental|damage"
        enum method "transfer|ewallet|cash"
        enum status
        int handled_by FK
    }
    cancellation_requests {
        int id PK
        int booking_id FK
        text reason
        enum status "pending|approved|rejected"
        int reviewed_by FK
        decimal refund_amount
        enum refund_status
    }
    handover_checklists {
        int id PK
        int booking_id FK
        enum type "pickup|return"
        int handled_by FK
    }
    handover_items {
        int id PK
        int checklist_id FK
        int booking_item_id FK
        int qty_good
        int qty_damaged
        int qty_lost
    }
    damage_reports {
        int id PK
        int booking_id FK
        int booking_item_id FK
        enum type "damaged|lost"
        decimal charge_amount "NULL = belum ditentukan"
        enum status "unsettled|settled"
    }
    articles {
        int id PK
        int author_id FK
        varchar slug UK
        enum status "draft|published"
    }
    audit_logs {
        bigint id PK
        int user_id FK
        varchar action
        varchar entity_type
        int entity_id
        text meta "JSON"
    }
    remember_tokens {
        int id PK
        int user_id FK
        char selector UK
        char validator_hash
    }
    login_attempts {
        int id PK
        varchar email
        varchar ip_address
    }
```

## Kamus Data Ringkas

| Tabel | Fungsi | Catatan desain |
|---|---|---|
| `users` | Akun semua role | Email unik; password bcrypt (`password_hash`); `is_active` untuk menonaktifkan tanpa menghapus |
| `remember_tokens` | Token "Ingat saya" | Hanya hash SHA-256 validator yang disimpan; token dirotasi setiap dipakai |
| `login_attempts` | Pembatas brute-force login & cek booking | Dibersihkan otomatis setelah 1 hari |
| `categories` | Kategori alat | Slug unik untuk URL; tidak bisa dihapus bila berisi alat |
| `equipment` | Alat camping | `stock_total` = unit fisik dimiliki; ketersediaan per tanggal dihitung, tidak disimpan |
| `packages`, `package_items` | Paket sewa | Diskon persentase 0–100 (`CHECK`) |
| `bookings` | Transaksi sewa | `user_id` NULL untuk tamu; status ENUM; tanggal tiap tahap; deposit administratif |
| `booking_items` | Rincian alat per booking | Tarif disalin (snapshot) agar perubahan tarif tidak mengubah transaksi lama |
| `payments` | Pembayaran simulasi | `purpose` membedakan sewa dan penggantian; `handled_by` untuk tunai |
| `cancellation_requests` | Pengajuan pembatalan | Terpisah dari booking agar riwayat, peninjau, dan catatan refund tersimpan |
| `handover_checklists`, `handover_items` | Checklist serah terima | Satu checklist per jenis per booking (`UNIQUE booking_id, type`) |
| `damage_reports` | Kerusakan/kehilangan | Nominal opsional; `unsettled` memblokir penyelesaian booking |
| `articles` | Artikel | `published_at` diisi saat pertama kali terbit |
| `audit_logs` | Catatan tindakan Staff/Admin | `meta` berisi JSON detail perubahan |

## Mengapa Stok Tidak Disimpan sebagai "Sisa Stok"

Ketersediaan selalu dihitung dari booking yang statusnya menahan stok (`pending_payment` yang belum lewat batas bayar, `confirmed`, `picked_up`). Dengan cara ini:

- pembatalan, kedaluwarsa, dan pengembalian otomatis melepas stok tanpa perlu memperbarui kolom lain;
- tidak ada risiko angka "sisa stok" tidak sinkron dengan transaksi;
- ketersediaan bisa dihitung untuk tanggal mana pun.

Indeks `idx_bookings_status_dates (status, start_date, end_date)` dan `idx_booking_items_equipment` mendukung query ketersediaan.

## Memperbarui Database yang Sudah Ada

`schema.sql` menghapus tabel. Untuk database yang datanya ingin dipertahankan, **jangan** menjalankan `schema.sql`/`seed.sql` ulang. Tambahan artikel awal dapat diimpor dengan aman lewat `seed_articles.sql` (tidak menghapus data dan tidak menggandakan artikel yang sudah ada).
