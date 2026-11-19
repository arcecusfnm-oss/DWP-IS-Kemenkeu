@extends('layouts.bidang')

@section('title', 'Tambah Kegiatan')

@section('content')
@php
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();

    $terkunci = $program !== null;
    $mulaiProgram   = $terkunci ? substr((string) $program->tanggal_mulai, 0, 10) : '';
    $selesaiProgram = $terkunci ? substr((string) $program->tanggal_selesai, 0, 10) : '';
    $periode = $terkunci
        ? \Carbon\Carbon::parse($mulaiProgram)->format('d-m-Y') . ' - ' . \Carbon\Carbon::parse($selesaiProgram)->format('d-m-Y')
        : null;

    $actionForm = $terkunci
        ? route('program-kerja.kegiatan.store', ['id' => $program->id_program_kerja])
        : route('kegiatan.store');
@endphp

<div class="content bidang-page">

    {{-- HEADER --}}
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <div class="program-header-info">
                    <h1>Tambah Kegiatan</h1>
                    <span class="program-bidang-badge">{{ $namaBidang ?? 'Bidang' }}</span>
                </div>
                <p id="infoProgramHeader">
                    @if ($terkunci)
                        Program: <strong>{{ $program->nama_program }}</strong>
                         •  Periode: {{ $periode }}
                    @else
                        Pilih program kerja bidang Anda untuk menambahkan kegiatan.
                    @endif
                </p>
            </div>

            <span class="pk-chip-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Semua kolom bertanda (*) wajib dilengkapi.
            </span>
        </div>
    </div>

    @if (session('galat'))
        <div class="bidang-alert">{{ session('galat') }}</div>
    @endif

    <form action="{{ $actionForm }}" method="POST"
          class="akun-form pk-form {{ $err ? 'ada-error' : '' }}" novalidate>
        @csrf

        <div class="akun-form-judul">
            <h2><i></i> Informasi Kegiatan</h2>
            <span>* Kolom wajib diisi</span>
        </div>

        @if ($err)
            <div class="akun-alert-error" role="alert">
                <x-icon name="x-circle" :size="22" />
                <div>
                    <strong>Periksa kembali isian Anda.</strong>
                    <span>Terdapat beberapa kolom wajib yang belum diisi dengan benar.</span>
                </div>
            </div>
        @endif

        {{-- Program kerja: terkunci jika dari baris program kerja, dropdown jika dari sidebar --}}
        @if ($terkunci)
            @php
                $teksProgram = $program->nama_program
                    . ($program->anggaran !== null ? ' (Pagu: Rp ' . number_format($program->anggaran, 0, ',', '.') . ')' : '');
            @endphp
            <div class="akun-field">
                <label for="program_tampil">Program Kerja</label>
                <div class="akun-input akun-readonly">
                    <input type="text" id="program_tampil" value="{{ $teksProgram }}" disabled>
                    <svg class="akun-lock" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                </div>
                <p class="akun-hint">Terkait otomatis dengan program kerja</p>
            </div>
        @else
            <div class="akun-field {{ isset($err['id_program_kerja']) ? 'has-error' : '' }}">
                <label for="id_program_kerja">Program Kerja <em>*</em></label>
                <div class="akun-input">
                    <select id="id_program_kerja" name="id_program_kerja">
                        <option value="">-- Pilih Program Kerja --</option>
                        @foreach ($daftarProgram as $item)
                            @php
                                $m = substr((string) $item->tanggal_mulai, 0, 10);
                                $s = substr((string) $item->tanggal_selesai, 0, 10);
                                $p = \Carbon\Carbon::parse($m)->format('d-m-Y') . ' - ' . \Carbon\Carbon::parse($s)->format('d-m-Y');
                                $labelOpsi = $item->nama_program
                                    . ($item->anggaran !== null ? ' (Pagu: Rp ' . number_format($item->anggaran, 0, ',', '.') . ')' : '');
                            @endphp
                            <option value="{{ $item->id_program_kerja }}"
                                    data-nama="{{ $item->nama_program }}"
                                    data-mulai="{{ $m }}"
                                    data-selesai="{{ $s }}"
                                    data-periode="{{ $p }}"
                                    @selected((string) old('id_program_kerja') === (string) $item->id_program_kerja)>
                                {{ $labelOpsi }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @isset($err['id_program_kerja'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['id_program_kerja'] }}</p>
                @elseif ($daftarProgram->isEmpty())
                    <p class="akun-hint">Belum ada program kerja pada bidang Anda. Tambahkan program kerja terlebih dahulu.</p>
                @endisset
            </div>
        @endif

        {{-- Nama kegiatan --}}
        <div class="akun-field {{ isset($err['nama_kegiatan']) ? 'has-error' : '' }}">
            <label for="nama_kegiatan">Nama Kegiatan <em>*</em></label>
            <div class="akun-input">
                <input type="text" id="nama_kegiatan" name="nama_kegiatan" maxlength="255"
                       placeholder="Contoh: Workshop Manajemen Keuangan Digital"
                       value="{{ old('nama_kegiatan') }}">
            </div>
            @isset($err['nama_kegiatan'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['nama_kegiatan'] }}</p>
            @endisset
        </div>

        {{-- Deskripsi --}}
        <div class="akun-field {{ isset($err['deskripsi']) ? 'has-error' : '' }}">
            <label for="deskripsi">Deskripsi <em>*</em></label>
            <div class="akun-input">
                <textarea id="deskripsi" name="deskripsi" maxlength="5000" rows="4"
                          placeholder="Jelaskan rincian agenda, materi pokok, atau tahapan teknis kegiatan ini...">{{ old('deskripsi') }}</textarea>
            </div>
            @isset($err['deskripsi'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['deskripsi'] }}</p>
            @endisset
        </div>

        {{-- Tanggal mulai + selesai --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['tanggal_mulai']) ? 'has-error' : '' }}">
                <label for="tanggal_mulai">Tanggal Mulai <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                           @if ($mulaiProgram) min="{{ $mulaiProgram }}" @endif
                           @if ($selesaiProgram) max="{{ $selesaiProgram }}" @endif
                           value="{{ old('tanggal_mulai') }}">
                </div>
                @isset($err['tanggal_mulai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_mulai'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['tanggal_selesai']) ? 'has-error' : '' }}">
                <label for="tanggal_selesai">Tanggal Selesai <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                           @if ($mulaiProgram) min="{{ $mulaiProgram }}" @endif
                           @if ($selesaiProgram) max="{{ $selesaiProgram }}" @endif
                           value="{{ old('tanggal_selesai') }}">
                </div>
                @isset($err['tanggal_selesai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_selesai'] }}</p>
                @endisset
            </div>
        </div>

        <div class="akun-form-aksi">
            <a href="{{ $terkunci ? route('program-kerja.bidang-saya') : route('kegiatan.index') }}" class="akun-btn-batal">Batal</a>
            <button type="submit" class="akun-btn-simpan">Simpan</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const selectProgram = document.getElementById('id_program_kerja');
        const infoHeader = document.getElementById('infoProgramHeader');
        const tglMulai = document.getElementById('tanggal_mulai');
        const tglSelesai = document.getElementById('tanggal_selesai');

        function sinkronProgram() {
            if (!selectProgram) return;
            const opt = selectProgram.options[selectProgram.selectedIndex];

            if (!opt || !opt.value) {
                if (infoHeader) {
                    infoHeader.textContent = 'Pilih program kerja bidang Anda untuk menambahkan kegiatan.';
                }
                if (tglMulai) {
                    tglMulai.removeAttribute('min');
                    tglMulai.removeAttribute('max');
                }
                if (tglSelesai) {
                    tglSelesai.removeAttribute('min');
                    tglSelesai.removeAttribute('max');
                }
                return;
            }

            const nama = opt.dataset.nama || '';
            const mulai = opt.dataset.mulai || '';
            const selesai = opt.dataset.selesai || '';
            const periode = opt.dataset.periode || '';

            if (infoHeader) {
                infoHeader.innerHTML = '';
                infoHeader.append('Program: ');
                const strong = document.createElement('strong');
                strong.textContent = nama;
                infoHeader.append(strong, `\u00A0•\u00A0 Periode: ${periode}`);
            }

            if (tglMulai && mulai && selesai) {
                tglMulai.min = mulai;
                tglMulai.max = selesai;
            }
            if (tglSelesai && mulai && selesai) {
                tglSelesai.min = (tglMulai && tglMulai.value) ? tglMulai.value : mulai;
                tglSelesai.max = selesai;
            }
        }

        if (selectProgram) {
            selectProgram.addEventListener('change', sinkronProgram);
            sinkronProgram();
        }

        if (tglMulai && tglSelesai) {
            tglMulai.addEventListener('change', function () {
                if (tglMulai.value) {
                    tglSelesai.min = tglMulai.value;
                }
            });
        }
    })();
</script>
@endpush