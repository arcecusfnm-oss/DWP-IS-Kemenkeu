<h1>Profil Saya</h1>
@if ($anggota)
    <table>
        @foreach ($anggota as $kunci => $nilai)
            <tr><th>{{ $kunci }}</th><td>{{ $nilai }}</td></tr>
        @endforeach
    </table>
@else
    <p>Data anggota belum tersedia, hubungi Admin</p>
@endif