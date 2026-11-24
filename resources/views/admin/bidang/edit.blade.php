@extends('layouts.admin')

@section('title', 'Ubah Nama Bidang')

@section('content')
    <div class="content bidang-page">
        <div class="bidang-form-card">
            <div style="background: #fbf5f0; border: 1px solid #f1e3dc; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px;">
                <h1 style="margin: 0 0 6px 0; font-family: 'Gelasio', serif; font-size: 27px; color: var(--coklat-tua); font-weight: 700;">Ubah Nama Bidang</h1>
                <p style="margin: 0; color: #78665d; font-size: 14px;">Nama baru akan tampil di semua halaman yang memuat bidang ini.</p>
            </div>

            @if ($dikunci)
                <div class="pesan pesan-error" role="alert" style="margin-bottom: 16px;">
                    Nama bidang ini dipakai sistem dan tidak dapat diubah.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.bidang.update', $bidang->id_bidang) }}">
                @csrf
                @method('PUT')

                <div class="bidang-form-group">
                    <label for="nama_bidang">Nama Bidang <span>*</span></label>

                    <input type="text" id="nama_bidang" name="nama_bidang" maxlength="100"
                        value="{{ old('nama_bidang', $bidang->nama_bidang) }}"
                        class="@error('nama_bidang') is-invalid @enderror" @disabled($dikunci)>

                    @error('nama_bidang')
                        <div class="pesan pesan-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="bidang-form-actions">
                    <a href="{{ route('admin.bidang.index') }}" class="bidang-btn-batal">Batal</a>

                    <button type="submit" class="bidang-btn-simpan" @disabled($dikunci)>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection