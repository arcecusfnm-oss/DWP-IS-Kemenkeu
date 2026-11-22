{{--
    resources/views/surat/_isian.blade.php
    Satu isian form ubah surat (T-70). Dipakai oleh surat/edit.blade.php.
    Variabel dari induk: $m (konfigurasi isian), $nilai (closure nilai awal), $ikon (peta ikon SVG), $errors.
--}}
@php
    $n        = $m['nama'];
    $tipe     = $m['tipe'] ?? 'text';
    $ada      = $errors->has($n);
    $desc     = $ada ? "galat-$n" : "hint-$n";
    $nilaiIni = $tipe === 'file' ? '' : $nilai($n, $tipe);
@endphp
<div class="ubs-field {{ ($m['penuh'] ?? false) ? 'ubs-full' : '' }} {{ $ada ? 'is-error' : '' }}">
    <div class="ubs-labelrow">
        <label for="{{ $n }}">{{ $m['label'] }}@if ($m['wajib'] ?? false) <em>*</em>@endif</label>
        @if ($m['hitung'] ?? false)<span class="ubs-hitung" id="hitung-{{ $n }}" aria-live="polite"></span>@endif
    </div>

    @if ($tipe === 'textarea')
        <textarea class="ubs-textarea" id="{{ $n }}" name="{{ $n }}" rows="{{ $m['baris'] ?? 3 }}"
                  maxlength="{{ $m['maks'] }}" aria-describedby="{{ $desc }}"
                  @if ($ada) aria-invalid="true" @endif>{{ $nilaiIni }}</textarea>
    @else
        <div class="ubs-input {{ ($m['kiri'] ?? false) ? 'ubs-kiri' : '' }}">
            @if ($tipe === 'select')
                <select id="{{ $n }}" name="{{ $n }}" aria-describedby="{{ $desc }}"
                        @if ($ada) aria-invalid="true" @endif>
                    @foreach ($m['opsi'] as $v => $t)
                        <option value="{{ $v }}" @selected((string) $nilaiIni === (string) $v)>{{ $t }}</option>
                    @endforeach
                </select>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ikon['chevron'] !!}</svg>
            @elseif ($tipe === 'file')
                <input type="file" id="{{ $n }}" name="{{ $n }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                       aria-describedby="{{ $desc }}" @if ($ada) aria-invalid="true" @endif>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ikon[$m['ikon']] !!}</svg>
            @else
                <input type="{{ $tipe }}" id="{{ $n }}" name="{{ $n }}" value="{{ $nilaiIni }}"
                       @if ($m['wajib'] ?? false) required @endif
                       @if ($tipe !== 'date') maxlength="{{ $m['maks'] ?? 255 }}" @endif
                       aria-describedby="{{ $desc }}" @if ($ada) aria-invalid="true" @endif>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ikon[$m['ikon']] !!}</svg>
            @endif
        </div>
    @endif

    @error($n)
        <p class="ubs-galat" id="galat-{{ $n }}" role="alert">{{ $message }}</p>
    @else
        <p class="ubs-hint" id="hint-{{ $n }}">{{ $m['hint'] ?? '' }}</p>
    @enderror
</div>