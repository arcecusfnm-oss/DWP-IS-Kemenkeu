<?php

return [
    'peran'  => ['Admin', 'Penasihat', 'Pimpinan Bidang', 'Anggota Bidang', 'Bendahara'],
    'bidang' => ['Sekretariat', 'Bendahara', 'Sosial Budaya', 'Pendidikan', 'Ekonomi'],

    // Peran yang boleh dipilih di Buat Akun & Ubah Peran (tanpa Admin)
    'peran_dipilih' => ['Admin', 'Penasihat', 'Pimpinan Bidang', 'Anggota Bidang'],

    // Peran -> bidang yang boleh
    'peran_bidang' => [
        'Admin'           => ['Sekretariat'],
        'Penasihat'       => ['Sekretariat', 'Bendahara', 'Sosial Budaya', 'Pendidikan', 'Ekonomi'],
        'Pimpinan Bidang' => ['Sekretariat', 'Bendahara', 'Sosial Budaya', 'Pendidikan', 'Ekonomi'],
        'Anggota Bidang'  => ['Sekretariat', 'Bendahara', 'Sosial Budaya', 'Pendidikan', 'Ekonomi'],
        'Bendahara'       => ['Bendahara'],
    ],

    // Peran yang dibatasi 1 orang per slot (nilai sama = satu slot)
    'slot' => [
        'Penasihat' => [
            'Sekretariat'   => 'sekre-bendahara',
            'Bendahara'     => 'sekre-bendahara',
            'Sosial Budaya' => 'sosbud',
            'Pendidikan'    => 'pendidikan',
            'Ekonomi'       => 'ekonomi',
        ],
        'Pimpinan Bidang' => [
            'Sekretariat'   => 'sekretariat',
            'Bendahara'     => 'bendahara',
            'Sosial Budaya' => 'sosbud',
            'Pendidikan'    => 'pendidikan',
            'Ekonomi'       => 'ekonomi',
        ],
    ],
];