<?php

return [
    'peran' => ['Admin', 'Penanggung Jawab', 'Pimpinan Bidang', 'Anggota Bidang', 'Bendahara', 'Penasihat'],

    // Peran yang boleh dipilih di Buat Akun & Ubah Peran
    'peran_dipilih' => ['Admin', 'Penanggung Jawab', 'Pimpinan Bidang', 'Anggota Bidang', 'Penasihat'],

    // Dropdown Ubah Peran & peran turun saat Alih Admin (tanpa Admin)
    'peran_ubah' => ['Penanggung Jawab', 'Pimpinan Bidang', 'Anggota Bidang', 'Penasihat'],

    // Peran yang tidak punya bidang (id_bidang = NULL), jumlah akun bebas
    'peran_tanpa_bidang' => ['Penasihat'],

    // Peran yang bidangnya dikunci. Peran lain boleh semua bidang di tabel `bidang`.
    'peran_bidang_tetap' => ['Admin'     => ['Sekretariat'],'Bendahara' => ['Bendahara'],],

    // Peran yang dibatasi 1 orang per bidang (bidang baru otomatis ikut)
    'peran_satu_per_slot' => ['Pimpinan Bidang'],

    // Bidang yang berbagi satu slot untuk peran tertentu
    'slot_gabungan' => [],
    'peran_multi_bidang'  => ['Penanggung Jawab'],
];