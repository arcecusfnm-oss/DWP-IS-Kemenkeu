@extends('layouts.admin')

@section('title', 'Tambah Bidang')

@section('content')
    <div class="content bidang-page">
        <div class="bidang-form-card">

            <div class="bidang-form-head">
                <div>
                    <h1>Tambah Bidang</h1>
                    <p>Isi nama bidang baru agar bisa dipilih saat membuat akun.</p>
                </div>

                <span class="bidang-chip">Formulir Bidang</span>
            </div>

            <form method="POST" action="{{ route('admin.bidang.store') }}">
                @csrf

                <div class="bidang-form-group">
                    <label for="nama_bidang">
                        Nama Bidang <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="nama_bidang"
                        name="nama_bidang"
                        value="{{ old('nama_bidang') }}"
                        placeholder="Contoh: Bidang Pendidikan"
                        class="@error('nama_bidang') is-invalid @enderror"
                    >

                    @error('nama_bidang')
                        <div class="pesan pesan-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="bidang-form-actions">
                    <a href="{{ route('admin.bidang.index') }}" class="bidang-btn-batal">
                        Batal
                    </a>

                    <button type="submit" class="bidang-btn-simpan">
                        Simpan
                    </button>
                </div>
            </form>

        </div>
    </div>
@endsection
