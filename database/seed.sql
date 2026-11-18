-- =====================================================================
-- CampGear — data awal (akun demo + katalog contoh)
-- Jalankan setelah schema.sql.
--
-- Akun demo (password di-hash bcrypt, bukan teks asli):
--   Admin : admin@demo.test / Admin#2026
--   Staff : staff@demo.test / Staff#2026
--   User  : user@demo.test  / User#2026
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO users (id, name, email, phone, address, password_hash, role) VALUES
(1, 'Admin CampGear', 'admin@demo.test', '081200000001', 'Surakarta', '$2y$10$y4IH2oKaQ6gmh2mboVAM0ebpERm6SBb8rfJl2FvF/PHL..EoFKsc2', 'admin'),
(2, 'Staff CampGear', 'staff@demo.test', '081200000002', 'Surakarta', '$2y$10$b87wGuRlM7Td/YwTWlgUE.VtU9o9zSOjKic6MmqXXalKPU7VzijMS', 'staff'),
(3, 'Pelanggan Demo', 'user@demo.test',  '081200000003', 'Sukoharjo', '$2y$10$aX.YiDQNseeUD3ZamAA3l.y7i4uV9GpHY2ZGuJsDzdOevt9Wx.7U2', 'user');

INSERT INTO categories (id, name, slug, description, icon) VALUES
(1, 'Tenda',               'tenda',               'Tenda dome dan tenda keluarga untuk 2 sampai 6 orang.', 'bi-house-door'),
(2, 'Tas & Carrier',       'tas-carrier',         'Carrier dan daypack untuk pendakian dan perjalanan.',   'bi-backpack'),
(3, 'Perlengkapan Tidur',  'perlengkapan-tidur',  'Sleeping bag, matras, dan bantal tiup.',                'bi-moon-stars'),
(4, 'Peralatan Masak',     'peralatan-masak',     'Kompor portable, nesting, dan perlengkapan dapur.',     'bi-fire'),
(5, 'Penerangan',          'penerangan',          'Headlamp, senter, dan lampu tenda.',                     'bi-lightbulb'),
(6, 'Perlengkapan Lainnya','perlengkapan-lainnya','Kursi, meja lipat, hammock, dan trekking pole.',        'bi-compass');

INSERT INTO equipment (id, category_id, name, slug, description, stock_total, daily_rate) VALUES
(1,  1, 'Tenda Dome 2 Orang',      'tenda-dome-2-orang',      'Tenda dome double layer kapasitas 2 orang. Rangka fiberglass, flysheet tahan air 2000 mm, berat sekitar 2,3 kg. Cocok untuk pendakian.', 8, 35000),
(2,  1, 'Tenda Dome 4 Orang',      'tenda-dome-4-orang',      'Tenda dome double layer kapasitas 4 orang dengan teras kecil. Flysheet tahan air 2000 mm dan dua pintu.', 5, 55000),
(3,  1, 'Tenda Family 6 Orang',    'tenda-family-6-orang',    'Tenda keluarga kapasitas 6 orang dengan dua kamar dan ruang tengah. Ideal untuk camping ground.', 3, 85000),
(4,  2, 'Carrier 60 Liter',        'carrier-60-liter',        'Carrier 60 L dengan backsystem adjustable, rain cover, dan hip belt empuk. Untuk pendakian 2–4 hari.', 10, 30000),
(5,  2, 'Carrier 45 Liter',        'carrier-45-liter',        'Carrier 45 L ringan untuk pendakian singkat atau tektok. Dilengkapi rain cover.', 8, 25000),
(6,  3, 'Sleeping Bag Polar',      'sleeping-bag-polar',      'Sleeping bag model mummy dengan lapisan polar, nyaman sampai suhu sekitar 10 °C.', 15, 15000),
(7,  3, 'Matras Foil',             'matras-foil',             'Matras gulung dengan lapisan foil untuk menahan dingin dari tanah.', 20, 5000),
(8,  4, 'Kompor Portable',         'kompor-portable',         'Kompor gas portable lipat dengan sistem pemantik. Kompatibel dengan gas kaleng 230 g (gas tidak termasuk).', 10, 15000),
(9,  4, 'Nesting Set 2–3 Orang',   'nesting-set',             'Satu set panci, wajan, dan mangkuk aluminium yang bisa disusun ringkas. Untuk 2–3 orang.', 10, 12000),
(10, 5, 'Headlamp LED',            'headlamp-led',            'Headlamp LED dengan tiga mode cahaya, baterai AAA (baterai tidak termasuk).', 12, 8000),
(11, 5, 'Lampu Tenda Gantung',     'lampu-tenda-gantung',     'Lampu LED gantung yang bisa diisi ulang lewat USB, menyala sampai sekitar 8 jam.', 10, 7000),
(12, 6, 'Kursi Lipat',             'kursi-lipat',             'Kursi lipat portable rangka aluminium, beban maksimal 100 kg.', 12, 10000),
(13, 6, 'Meja Lipat',              'meja-lipat',              'Meja lipat aluminium ukuran 60 x 40 cm, ringan dan mudah dibawa.', 6, 15000),
(14, 6, 'Hammock',                 'hammock',                 'Hammock parasut kapasitas satu orang, sudah termasuk tali webbing.', 8, 10000),
(15, 6, 'Trekking Pole',           'trekking-pole',           'Tongkat trekking aluminium yang bisa diatur panjangnya. Disewakan per batang.', 10, 10000);

INSERT INTO packages (id, name, slug, description, discount_percent) VALUES
(1, 'Paket Solo Hiking',    'paket-solo-hiking',    'Perlengkapan inti untuk satu orang yang ingin mendaki dan bermalam di gunung.', 10),
(2, 'Paket Berdua',         'paket-berdua',         'Perlengkapan menginap untuk dua orang, lengkap dengan alat masak dan lampu.', 10),
(3, 'Paket Family Camping', 'paket-family-camping', 'Camping keluarga di camping ground: tenda besar, alat tidur, alat masak, dan furnitur.', 15);

INSERT INTO package_items (package_id, equipment_id, quantity) VALUES
(1, 1, 1), (1, 5, 1), (1, 6, 1), (1, 7, 1), (1, 10, 1),
(2, 1, 1), (2, 6, 2), (2, 7, 2), (2, 8, 1), (2, 9, 1), (2, 11, 1),
(3, 3, 1), (3, 6, 4), (3, 7, 4), (3, 8, 1), (3, 9, 1), (3, 11, 2), (3, 12, 4), (3, 13, 1);
