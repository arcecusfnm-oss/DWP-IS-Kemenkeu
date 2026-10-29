@extends('layouts.guest')

@section('title', 'Beranda')
@section('body-class', 'halaman-landing')

@push('styles')
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')
    {{-- HEADER --}}
    <header class="lp-header">
        <a href="#beranda" class="lp-brand">
            <img src="{{ asset('images/logo-dwp.png') }}" alt="Logo DWP">
            <span>
                <strong>DWP Kemenkeu</strong>
                <small>Kementrian Keuangan</small>
            </span>
        </a>

        <nav class="lp-nav">
            <a href="#beranda">Beranda</a>
            <a href="#visi-misi">Visi &amp; Misi</a>
            <a href="#kontak">Kontak</a>
        </nav>

        <div class="lp-aksi">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="lp-masuk">Keluar <i class="bi bi-box-arrow-right"></i></button>
                </form>
            @else
                <a href="{{ route('login') }}" class="lp-masuk">Masuk <i class="bi bi-box-arrow-in-right"></i></a>
            @endauth
        </div>
    </header>

    {{-- HERO --}}
    <section id="beranda" class="lp-hero">
        <img src="{{ asset('images/foto-utama.jpg') }}" alt="Kegiatan DWP Kemenkeu" class="lp-hero-foto">
    </section>
    <div class="lp-strip"></div>

    {{-- SELAMAT DATANG + VISI MISI --}}
    <section id="visi-misi" class="lp-selamat">
        <div class="lp-judul reveal">
            <p>Selamat Datang di Organisasi</p>
            <h1>Dharma Wanita Persatuan</h1>
            <h2>Kementrian Keuangan</h2>
        </div>

        <div class="lp-grid">
            <figure class="lp-ketua reveal">
                <img src="{{ asset('images/ketua.png') }}" alt="Ibu Wati Heru Pambudi">
                <figcaption>
                    <strong>Ibu Wati Heru Pambudi</strong>
                    <span>Ketua Dharma Wanita Persatuan<br>Kementrian Keuangan</span>
                </figcaption>
            </figure>

            <div class="lp-visimisi reveal">
                <i class="bi bi-quote kutip-buka"></i>

                <h3>Visi</h3>
                <p class="lp-visi">“Menjadi organisasi istri pegawai Aparatur Sipil Negara yang profesional untuk memperkuat peran serta perempuan dalam pembangunan bangsa.”</p>

                <h3>Misi</h3>
                <ul>
                    <li>Mengembangkan SDM Dharma Wanita Persatuan yang berkualitas dan berwawasan global.</li>
                    <li>Mensejahterakan anggota, keluarga dan masyarakat melalui Bidang Pendidikan, Bidang Ekonomi dan Bidang Sosial Budaya secara demokratis.</li>
                    <li>Meningkatkan kerjasama multipihak dalam pelaksanaan program kerja Dharma Wanita Persatuan.</li>
                    <li>Mengembangkan sistem informasi manajemen Dharma Wanita Persatuan secara terintegrasi.</li>
                </ul>

                <i class="bi bi-quote kutip-tutup"></i>
            </div>
        </div>
    </section>

    {{-- INSTAGRAM --}}
    <section class="lp-ig reveal">
        <div class="lp-ig-bingkai">
            <blockquote class="instagram-media"
                data-instgrm-permalink="https://www.instagram.com/dwp.kemenkeu/"
                data-instgrm-version="14">
                <a href="https://www.instagram.com/dwp.kemenkeu/" target="_blank" rel="noopener">
                    Lihat profil @dwp.kemenkeu di Instagram
                </a>
            </blockquote>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer id="kontak" class="lp-footer">
        <div class="lp-footer-isi">
            <div class="lp-alamat">
                <h4>Dharma Wanita Persatuan<br>Kementrian Keuangan</h4>
                <p>Jl. Dr. GSSJ Ratulangi No.17, RT.2/RW.3, Gondangdia, Kec. Menteng,<br>Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10350</p>
            </div>
            <div class="lp-kontak">
                <h4>Kontak</h4>
                <a href="mailto:dharmawanitapersatuanpusat@gmail.com"><i class="bi bi-envelope"></i> dharmawanitapersatuanpusat@gmail.com</a>
                <a href="tel:+62215201714"><i class="bi bi-telephone"></i> Kantor (021) 5201714</a>
                <a href="https://wa.me/6282121441162" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Sekretariat (+62) 821 2144-1162</a>
            </div>
        </div>
        <div class="lp-hakcipta">© 2026 DWP Kemenkeu. Hak Cipta Dilindungi.</div>
    </footer>
@endsection

@push('scripts')
<script async src="https://www.instagram.com/embed.js"></script>
<script>
    // Efek muncul halus saat di-scroll
    const items = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('tampil'); io.unobserve(e.target); }
        });
    }, { threshold: 0.15 });
    items.forEach(el => io.observe(el));
</script>
@endpush