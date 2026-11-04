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
                            <th>NO</th>
                            <th>NAMA BIDANG</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($bidangs as $index => $bidang)
                            <tr>
                                <td>{{ $index + 1 }}</td>

                                <td>{{ $bidang->nama_bidang }}</td>

                                <td>
                                    <div class="bidang-aksi">
                                        <a href="{{ route('admin.bidang.edit', $bidang->id_bidang) }}"
                                            aria-label="Edit bidang">
                                            Edit
                                        </a>

                                        <form method="POST"
                                            action="{{ route('admin.bidang.destroy', $bidang->id_bidang) }}"
                                            onsubmit="return confirm('Yakin ingin menghapus bidang ini?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit">Hapus</button>
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
