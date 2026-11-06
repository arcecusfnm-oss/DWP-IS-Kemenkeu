```php
@extends('layouts.admin')

@section('title', 'Kelola Bidang')

@section('content')
    <div class="content bidang-page">

        {{-- Card Header --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div>
                    <h1>Kelola Bidang</h1>
                    <p>Kelola bidang organisasi DWP-IS Kemenkeu.</p>
                </div>

                <a href="{{ route('admin.bidang.create') }}" class="bidang-btn-tambah">
                    <span>+</span>
                    Tambah Bidang
                </a>
            </div>
        </div>

        {{-- Notifikasi Berhasil --}}
        @if (session('success'))
            <div class="pesan pesan-sukses" role="alert">
                {{ session('success') }}
            </div>
        @endif

        {{-- Card Tabel Bidang --}}
        <div class="bidang-card">
            <div class="bidang-table-wrapper">
                <table class="bidang-table">
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">NO</th>
                            <th>NAMA BIDANG</th>
                            <th style="width: 200px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($bidangs as $index => $bidang)
                            <tr>
                                <td>{{ $index + 1 }}</td>

                                <td>{{ $bidang->nama_bidang }}</td>

                                <td style="text-align: center;">
                                    <div class="bidang-aksi" style="display: inline-flex; align-items: center; justify-content: center; gap: 16px;">
                                        {{-- Tombol Edit --}}
                                        <a href="{{ route('admin.bidang.edit', $bidang->id_bidang) }}"
                                            aria-label="Edit bidang" style="display: inline-flex; align-items: center; gap: 6px; color: var(--coklat-tua); text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            Edit
                                        </a>

                                        {{-- Tombol Hapus --}}
                                        <form method="POST"
                                            action="{{ route('admin.bidang.destroy', $bidang->id_bidang) }}"
                                            onsubmit="return confirm('Yakin ingin menghapus bidang ini?')" style="margin: 0; display: inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Hapus bidang" style="display: inline-flex; align-items: center; gap: 6px; background: transparent; border: none; cursor: pointer; color: #b94b3c; font-weight: 600; font-size: 13px; padding: 0;">
                                                                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>

                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="bidang-kosong">
                                    Belum ada data bidang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
