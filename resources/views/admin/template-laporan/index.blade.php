@extends('layouts.admin')

@section('title', 'Kelola Templat Laporan')

@section('content')
<div class="content akun-page template-page">
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <h1>Kelola Templat Laporan</h1>
                <p>Atur nama kolom yang digunakan pada formulir laporan kegiatan seluruh bidang.</p>
            </div>
        </div>
    </div>

    <div class="template-card">
        <div class="template-card-heading">
            <div>
                <h2>Konfigurasi Kolom Laporan</h2>
                <p>Perubahan templat berlaku secara global untuk formulir laporan kegiatan berikutnya.</p>
            </div>
        </div>

        <div id="templateNotice" class="template-notice" role="status" hidden></div>

        <form id="templateForm" novalidate>
            @csrf
            <div class="template-fields">
                <div class="template-field">
                    <label for="label_judul_kegiatan">Judul Kegiatan</label>
                    <input type="text" id="label_judul_kegiatan" name="label_judul_kegiatan"
                        maxlength="255" required aria-describedby="label_judul_kegiatan-error">
                    <p class="akun-error" id="label_judul_kegiatan-error" role="alert" hidden><x-icon name="x-circle" :size="14" /> <span></span></p>
                    <small>Nama kolom judul kegiatan pada formulir laporan.</small>
                </div>

                <div class="template-field">
                    <label for="label_tanggal_kegiatan">Tanggal Kegiatan</label>
                    <input type="text" id="label_tanggal_kegiatan" name="label_tanggal_kegiatan"
                        maxlength="255" required aria-describedby="label_tanggal_kegiatan-error">
                    <p class="akun-error" id="label_tanggal_kegiatan-error" role="alert" hidden><x-icon name="x-circle" :size="14" /> <span></span></p>
                    <small>Nama kolom tanggal pelaksanaan kegiatan.</small>
                </div>

                <div class="template-field">
                    <label for="label_penjelasan_singkat">Penjelasan Singkat</label>
                    <input type="text" id="label_penjelasan_singkat" name="label_penjelasan_singkat"
                        maxlength="255" required aria-describedby="label_penjelasan_singkat-error">
                    <p class="akun-error" id="label_penjelasan_singkat-error" role="alert" hidden><x-icon name="x-circle" :size="14" /> <span></span></p>
                    <small>Nama kolom penjelasan atau deskripsi kegiatan.</small>
                </div>

                <div class="template-field">
                    <label for="label_dokumentasi">Dokumentasi</label>
                    <input type="text" id="label_dokumentasi" name="label_dokumentasi"
                        maxlength="255" required aria-describedby="label_dokumentasi-error">
                    <p class="akun-error" id="label_dokumentasi-error" role="alert" hidden><x-icon name="x-circle" :size="14" /> <span></span></p>
                    <small>Nama kolom dokumentasi kegiatan.</small>
                </div>
            </div>

            <div class="template-actions">
                <span id="templateUpdated" class="template-updated"></span>
                <button type="submit" id="templateSave" class="bidang-btn-simpan template-save-btn">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .template-card {
        background: #fff;
        border: 1px solid #e9e5df;
        border-radius: 14px;
        padding: clamp(18px, 4vw, 30px);
        box-shadow: 0 3px 14px rgba(30, 30, 30, .04);
    }
    .template-card-heading {
        padding-bottom: 20px;
        margin-bottom: 22px;
        border-bottom: 1px solid #eeeae5;
    }
    .template-card-heading h2 {
        margin: 0;
        color: #333;
        font-size: 20px;
    }
    .template-card-heading p {
        color: #777;
        font-size: 14px;
        line-height: 1.6;
        margin: 8px 0 0;
    }
    .template-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 22px;
    }
    .template-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    .template-field label {
        color: #3b3b3b;
        font-size: 14px;
        font-weight: 600;
    }
    .template-field input {
        box-sizing: border-box;
        width: 100%;
        min-height: 44px;
        border: 1px solid #d9d5cf;
        border-radius: 8px;
        padding: 10px 12px;
        font: inherit;
        color: #333;
        background: #fff;
    }
    .template-field input:focus {
        outline: none;
        border-color: #92764b;
        box-shadow: 0 0 0 3px rgba(146, 118, 75, .13);
    }
    .template-field small {
        color: #888;
        font-size: 12px;
        line-height: 1.5;
    }
    .template-notice {
        margin-bottom: 20px;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 14px;
        line-height: 1.5;
    }
    .template-notice.success {
        color: #24633c;
        background: #edf7f0;
        border: 1px solid #cde7d4;
    }
    .template-notice.error {
        color: #8a3535;
        background: #fff0f0;
        border: 1px solid #f0d0d0;
    }
    .template-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid #eeeae5;
    }
    .template-updated {
        color: #888;
        font-size: 12px;
    }
    @media (max-width: 600px) {
        .template-page {
            padding-top: 0;
        }
        .template-fields {
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
        }
        .template-actions {
            align-items: stretch;
            flex-direction: column;
        }
    }


</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('templateForm');
    const notice = document.getElementById('templateNotice');
    const saveButton = document.getElementById('templateSave');
    const updatedText = document.getElementById('templateUpdated');

    const endpoint = @json(route('admin.template-laporan.show'));
    const saveEndpoint = @json(route('admin.template-laporan.update'));

    const fields = [
        'label_judul_kegiatan',
        'label_tanggal_kegiatan',
        'label_penjelasan_singkat',
        'label_dokumentasi'
    ];

    function showNotice(message, type) {
        notice.textContent = message;
        notice.className = 'template-notice ' + type;
        notice.hidden = false;
    }

    function hideNotice() {
        notice.hidden = true;
        notice.textContent = '';
        notice.className = 'template-notice';
    }

    function tampilkanError(input, errId, pesan) {
        const el = document.getElementById(errId);
        if (!input || !el) return;

        el.querySelector('span').textContent = pesan;
        el.hidden = false;
        input.closest('.template-field').classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
    }

    function bersihkanError(input, errId) {
        const el = document.getElementById(errId);
        if (!input || !el) return;

        el.querySelector('span').textContent = '';
        el.hidden = true;
        input.closest('.template-field').classList.remove('has-error');
        input.removeAttribute('aria-invalid');
    }

    function showFieldError(field, message) {
        const input = document.getElementById(field);
        tampilkanError(input, field + '-error', message);
    }

    function clearFieldError(field) {
        const input = document.getElementById(field);
        bersihkanError(input, field + '-error');
    }

    function validateFields() {
        let valid = true;

        fields.forEach((field) => {
            const input = document.getElementById(field);
            const label = form.querySelector('label[for="' + field + '"]').textContent.trim();

            if (!input.value.trim()) {
                showFieldError(field, label + ' wajib diisi.');
                valid = false;
            } else {
                clearFieldError(field);
            }
        });

        return valid;
    }

    fields.forEach((field) => {
        document.getElementById(field).addEventListener('input', () => {
            if (document.getElementById(field).value.trim()) {
                clearFieldError(field);
            }
        });
    });

    function setLoading(loading) {
        saveButton.disabled = loading;
        saveButton.textContent = loading ? 'Menyimpan...' : 'Simpan Perubahan';
    }

    async function loadTemplate() {
        setLoading(true);
        hideNotice();

        try {
            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Templat laporan gagal dimuat.');
            }

            fields.forEach((field) => {
                const input = document.getElementById(field);
                if (input) input.value = result[field] ?? '';
            });

            if (result.diperbarui_pada) {
                updatedText.textContent = 'Terakhir diperbarui: ' + result.diperbarui_pada;
            }
        } catch (error) {
            showNotice(error.message || 'Terjadi kesalahan saat memuat templat.', 'error');
        } finally {
            setLoading(false);
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideNotice();

        if (!validateFields()) return;

        const payload = {};
        fields.forEach((field) => {
            payload[field] = document.getElementById(field).value.trim();
        });

        if (fields.some((field) => !payload[field])) {
            showNotice('Semua nama kolom wajib diisi.', 'error');
            return;
        }

        setLoading(true);

        try {
            const response = await fetch(saveEndpoint, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok) {
                const validationMessages = result.errors
                    ? Object.values(result.errors).flat().join(' ')
                    : null;
                throw new Error(validationMessages || result.message || 'Templat gagal disimpan.');
            }

            showNotice(result.message || 'Templat laporan berhasil diperbarui.', 'success');

            if (result.diperbarui_pada) {
                updatedText.textContent = 'Terakhir diperbarui: ' + result.diperbarui_pada;
            }
        } catch (error) {
            showNotice(error.message || 'Terjadi kesalahan saat menyimpan templat.', 'error');
        } finally {
            setLoading(false);
        }
    });

    loadTemplate();
})();
</script>
@endpush
