-- =====================================================================
-- CampGear — empat artikel informatif awal.
-- Aman dijalankan berulang kali: artikel hanya ditambahkan bila slug-nya
-- belum ada (INSERT ... WHERE NOT EXISTS). Tidak menghapus data apa pun.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO articles (author_id, title, slug, excerpt, body, status, published_at)
SELECT (SELECT id FROM users WHERE email = 'staff@demo.test' LIMIT 1),
'Checklist Perlengkapan Camping untuk Pemula',
'checklist-perlengkapan-camping-pemula',
'Daftar perlengkapan inti yang perlu disiapkan sebelum camping pertama, dikelompokkan berdasarkan fungsi agar tidak ada yang tertinggal.',
'Camping pertama sering terasa merepotkan karena banyak hal yang harus dibawa. Kuncinya adalah menyusun perlengkapan berdasarkan fungsi: tempat berteduh, tidur, memasak, penerangan, dan keselamatan. Dengan cara ini kamu bisa memeriksa satu per satu tanpa ada yang terlewat.

## 1. Tempat berteduh
- Tenda dengan kapasitas satu tingkat di atas jumlah orang, misalnya tenda 4 orang untuk 3 orang, agar ada ruang untuk tas.
- Flysheet atau lapisan luar tenda untuk menahan hujan dan embun.
- Pasak cadangan dan tali, karena pasak mudah hilang atau bengkok di tanah keras.

## 2. Perlengkapan tidur
- Sleeping bag yang sesuai suhu lokasi. Di dataran tinggi suhu malam bisa turun di bawah 10 °C.
- Matras sebagai penahan dingin dari tanah. Tanpa matras, panas tubuh cepat hilang meskipun sleeping bag tebal.
- Pakaian ganti kering khusus untuk tidur, disimpan di kantong kedap air.

## 3. Memasak dan minum
- Kompor portable, gas kaleng, dan pemantik cadangan.
- Nesting atau panci ringkas, sendok, gelas, dan kantong sampah.
- Air minum minimal 2 liter per orang per hari, ditambah air untuk memasak.

## 4. Penerangan
- Headlamp untuk setiap orang, karena kedua tangan tetap bebas saat beraktivitas.
- Lampu tenda untuk penerangan di area kemah.
- Baterai cadangan atau power bank.

## 5. Keselamatan dan kesehatan
- Kotak P3K berisi plester, antiseptik, obat pribadi, dan perban elastis.
- Jas hujan, jaket hangat, dan topi.
- Peta lokasi atau aplikasi navigasi luring, serta nomor kontak darurat pengelola kawasan.

## Tips menyiapkan barang
1. Tulis checklist dua hari sebelum berangkat, bukan pada malam terakhir.
2. Coba pasang tenda di rumah untuk memastikan semua bagiannya lengkap.
3. Masukkan barang yang paling sering dipakai di bagian atas tas.
4. Timbang total bawaan. Untuk pendakian, usahakan beban tas tidak lebih dari seperempat berat badan.

Tidak semua perlengkapan harus dibeli. Untuk camping pertama, menyewa alat adalah cara hemat untuk mengetahui jenis perlengkapan yang cocok sebelum memutuskan membeli sendiri.',
'published', NOW() - INTERVAL 21 DAY
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM articles WHERE slug = 'checklist-perlengkapan-camping-pemula');

INSERT INTO articles (author_id, title, slug, excerpt, body, status, published_at)
SELECT (SELECT id FROM users WHERE email = 'staff@demo.test' LIMIT 1),
'Cara Memasang dan Merawat Tenda Dome',
'cara-memasang-dan-merawat-tenda-dome',
'Langkah memasang tenda dome yang kokoh dan tahan angin, ditambah cara merawatnya agar awet dan tidak berjamur.',
'Tenda dome adalah jenis tenda yang paling banyak dipakai untuk camping dan pendakian. Bentuknya yang melengkung membuat tenda ini stabil terhadap angin dan cepat dipasang. Meski begitu, pemasangan yang asal-asalan bisa membuat tenda bocor atau roboh saat cuaca berubah.

## Memilih lokasi
- Cari permukaan datar, sedikit lebih tinggi dari sekitarnya, agar air hujan tidak menggenang di bawah tenda.
- Singkirkan batu kecil dan ranting yang dapat merobek lantai tenda.
- Hindari mendirikan tenda tepat di bawah pohon yang memiliki dahan kering atau di jalur aliran air.
- Arahkan pintu tenda membelakangi arah datangnya angin.

## Langkah pemasangan
1. Bentangkan groundsheet atau alas, lalu letakkan badan tenda di atasnya.
2. Sambungkan frame atau tiang, lalu masukkan ke selongsong atau kaitkan ke klip secara menyilang.
3. Tancapkan ujung frame ke cincin di sudut tenda sehingga tenda berdiri.
4. Pasang pasak di setiap sudut dengan kemiringan sekitar 45 derajat menjauhi tenda.
5. Pasang flysheet dengan pintu yang sejajar pintu tenda, lalu kencangkan tali-talinya.
6. Pastikan flysheet tidak menempel pada dinding dalam tenda. Celah udara di antaranya mencegah embun merembes masuk.

## Saat hujan dan angin
- Kencangkan semua tali pengikat (guy rope) agar flysheet tidak berkibar.
- Jangan menyentuh dinding dalam tenda ketika basah, karena air dapat merembes lewat titik sentuhan.
- Simpan barang di tengah tenda, jauh dari dinding.

## Merawat tenda setelah dipakai
- **Jangan pernah menyimpan tenda dalam keadaan basah.** Jemur di tempat teduh sampai benar-benar kering agar tidak berjamur.
- Bersihkan tanah dan pasir dengan kain lembap. Hindari deterjen keras dan mesin cuci.
- Lipat frame dari bagian tengah agar tali elastis di dalamnya tidak cepat kendur.
- Periksa resleting dan jahitan. Kerusakan kecil sebaiknya segera diperbaiki sebelum melebar.

Kalau kamu menyewa tenda, laporkan kerusakan yang terjadi selama pemakaian saat pengembalian. Kondisi tenda akan diperiksa bersama staf sesuai checklist serah terima.',
'published', NOW() - INTERVAL 14 DAY
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM articles WHERE slug = 'cara-memasang-dan-merawat-tenda-dome');

INSERT INTO articles (author_id, title, slug, excerpt, body, status, published_at)
SELECT (SELECT id FROM users WHERE email = 'staff@demo.test' LIMIT 1),
'Tips Camping Aman Saat Musim Hujan',
'tips-camping-aman-saat-musim-hujan',
'Camping saat musim hujan tetap bisa nyaman asalkan persiapan, pemilihan lokasi, dan pengelolaan barang bawaan dilakukan dengan benar.',
'Musim hujan bukan alasan untuk membatalkan rencana camping. Suasana yang sejuk dan alam yang lebih hijau justru menjadi daya tarik tersendiri. Namun, risiko seperti hipotermia, banjir kecil di area kemah, dan jalur yang licin perlu diantisipasi sejak awal.

## Sebelum berangkat
- Pantau prakiraan cuaca dan informasi dari pengelola kawasan. Tunda perjalanan bila ada peringatan cuaca ekstrem.
- Pastikan tenda memiliki flysheet yang rapat dan lantai yang tahan air.
- Bawa jas hujan model terpisah (atasan dan celana), bukan ponco, agar tetap leluasa bergerak.
- Bungkus pakaian, sleeping bag, dan barang elektronik dengan kantong kedap air sebelum dimasukkan ke tas.

## Memilih lokasi kemah
- Hindari cekungan, tepi sungai, dan dasar lembah yang bisa menjadi jalur air.
- Jangan berkemah tepat di bawah tebing atau lereng curam yang rawan longsor.
- Buat sedikit jarak antartenda agar air tidak terperangkap di antaranya.

## Menjaga tubuh tetap hangat dan kering
1. Ganti pakaian basah segera setelah sampai di tenda.
2. Simpan satu set pakaian kering khusus untuk tidur dan jangan dipakai di luar tenda.
3. Konsumsi makanan dan minuman hangat secara teratur. Tubuh membutuhkan energi lebih untuk menjaga suhu.
4. Kenali tanda awal hipotermia: menggigil hebat, bicara melantur, dan gerakan melambat. Segera hangatkan penderita.

## Mengelola barang di dalam tenda
- Letakkan sepatu dan barang basah di teras tenda (vestibule), bukan di dalam ruang tidur.
- Gunakan matras sebagai lapisan tambahan agar dingin dan lembap dari tanah tidak langsung terasa.
- Buka ventilasi tenda sedikit untuk mengurangi embun di dinding dalam.

## Setelah pulang
- Jemur tenda, sleeping bag, dan matras hingga kering sebelum disimpan atau dikembalikan.
- Periksa perlengkapan dari lumpur dan kerusakan.

Persiapan yang baik membuat camping di musim hujan tetap aman dan menyenangkan. Pastikan juga kamu memberi tahu keluarga atau teman tentang rencana perjalanan dan perkiraan waktu kembali.',
'published', NOW() - INTERVAL 7 DAY
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM articles WHERE slug = 'tips-camping-aman-saat-musim-hujan');

INSERT INTO articles (author_id, title, slug, excerpt, body, status, published_at)
SELECT (SELECT id FROM users WHERE email = 'staff@demo.test' LIMIT 1),
'Cara Menyusun Isi Carrier agar Nyaman Dibawa',
'cara-menyusun-isi-carrier',
'Susunan isi carrier menentukan apakah beban terasa ringan dan stabil atau justru membuat bahu cepat pegal. Begini cara mengaturnya dari bawah sampai atas.',
'Carrier yang diisi asal-asalan terasa berat, menarik badan ke belakang, dan membuat bahu cepat pegal. Padahal dengan isi yang sama, susunan yang tepat membuat beban terasa jauh lebih ringan. Prinsipnya sederhana: **barang berat dekat punggung**, barang yang jarang dipakai di bawah, dan barang yang sering dipakai mudah dijangkau.

## Pilih ukuran yang sesuai
- Carrier 45 liter cukup untuk pendakian singkat atau satu malam dengan perlengkapan ringkas.
- Carrier 60 liter cocok untuk pendakian 2–4 hari yang membawa tenda, alat masak, dan logistik lebih banyak.
- Carrier yang terlalu besar membuat kita cenderung membawa barang yang tidak perlu.

## Bagian bawah: barang ringan untuk di kemah
- Sleeping bag yang sudah dimasukkan ke kantong kedap air.
- Pakaian tidur dan pakaian ganti.

Barang-barang ini baru dibutuhkan saat sampai di lokasi kemah, sekaligus menjadi alas yang empuk untuk isi di atasnya.

## Bagian tengah: barang paling berat
- Logistik makanan, air cadangan, nesting, dan kompor.
- Badan tenda tanpa frame, bila muat.

Letakkan barang berat sedekat mungkin dengan punggung, kira-kira setinggi tulang belikat. Titik berat yang dekat dengan tubuh membuat langkah lebih stabil, terutama di jalur menanjak.

## Bagian atas dan kantong kepala
- Jas hujan, jaket, dan kotak P3K di bagian paling atas.
- Headlamp, peta, ponsel, tabir surya, dan camilan di kantong kepala (lid).

## Kantong samping dan bagian luar
- Botol minum di kantong samping agar bisa diambil tanpa melepas carrier.
- Frame tenda atau trekking pole diikat dengan tali kompresi samping.
- Hindari menggantung banyak barang di luar karena mudah tersangkut dan membuat beban berayun.

## Atur tali sebelum berjalan
1. Kencangkan hip belt tepat di atas tulang panggul. Sebagian besar beban seharusnya bertumpu di pinggul, bukan di bahu.
2. Tarik shoulder strap hingga carrier menempel ke punggung, lalu atur load lifter di atas bahu.
3. Pasang sternum strap di dada agar strap bahu tidak melorot.
4. Kencangkan tali kompresi supaya isi tidak bergeser saat berjalan.

Sebagai patokan, usahakan berat total carrier tidak lebih dari sekitar 20% berat badan. Saat mengembalikan carrier sewaan, kosongkan semua kantong dan bersihkan dari tanah atau lumpur agar pemeriksaan kondisi berjalan cepat.',
'published', NOW() - INTERVAL 2 DAY
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM articles WHERE slug = 'cara-menyusun-isi-carrier');
