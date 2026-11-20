<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terjadi Kesalahan - DWP-IS Kemenkeu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #F8F3ED;
            font-family: 'Inter', sans-serif;
            color: #493126;
        }

        .kartu-error {
            position: relative;
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            text-align: center;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(73, 49, 38, .08);
        }

        .bar-jendela {
            display: flex;
            align-items: center;
            gap: 7px;
            height: 30px;
            padding: 0 16px;
            background: #F5B58F;
        }

        .bar-jendela span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #FFF9F4;
        }

        .isi-kartu {
            padding: 40px 36px 72px;
        }

        .kode-error {
            margin: 0 0 12px;
            color: #493126;
            font-size: 64px;
            font-weight: 700;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 23px;
            color: #493126;
        }

        .deskripsi {
            color: #75645A;
            font-size: 14px;
            line-height: 1.8;
        }

        .incident {
            margin: 28px 0;
            padding: 16px;
            border: 1px solid #F2D5C3;
            border-radius: 10px;
            background: #FFF9F4;
        }

        .incident-label {
            display: block;
            margin-bottom: 8px;
            color: #8B776B;
            font-size: 12px;
        }

        .incident-id {
            color: #A65F40;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .tombol {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            background: #F5B58F;
            color: #493126;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s ease;
        }

        .tombol:hover {
            background: #EBA47D;
        }

        .catatan {
            margin-top: 24px;
            color: #A18E82;
            font-size: 12px;
        }

        .maskot {
            position: absolute;
            right: 18px;
            bottom: 14px;
            width: 78px;
            height: auto;
            transform: rotate(8deg);
        }

        @media (max-width: 480px) {
            .isi-kartu {
                padding: 32px 24px 76px;
            }

            .kode-error {
                font-size: 56px;
            }

            h1 {
                font-size: 21px;
            }

            .maskot {
                right: 12px;
                bottom: 10px;
                width: 60px;
            }
        }
    </style>
</head>
<body>
    <main class="kartu-error">
        <div class="bar-jendela" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="isi-kartu">
            <p class="kode-error">500</p>

            <h1>Terjadi Kesalahan</h1>

            <p class="deskripsi">
                Maaf, terjadi kesalahan pada sistem.
                Silakan coba kembali beberapa saat lagi.
                Jika masalah terus terjadi, hubungi administrator
                dan sertakan ID kejadian berikut.
            </p>

            <div class="incident">
                <span class="incident-label">ID Kejadian</span>
                <span class="incident-id">{{ $incidentId }}</span>
            </div>

            <a href="{{ url('/') }}" class="tombol">
                Kembali ke Beranda
            </a>

            <p class="catatan">
                DWP-IS Kementerian Keuangan
            </p>
        </div>

        <svg class="maskot" viewBox="0 0 80 84" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
            <path d="M8 14 L50 6 L72 22 L66 72 L14 78 Z"
                  fill="#FFF9F4" stroke="#493126" stroke-width="2.5" stroke-linejoin="round"/>
            <path d="M50 6 L48 24 L72 22 Z"
                  fill="#F2D5C3" stroke="#493126" stroke-width="2.5" stroke-linejoin="round"/>
            <circle cx="30" cy="46" r="2.8" fill="#493126"/>
            <circle cx="51" cy="44" r="2.8" fill="#493126"/>
            <circle cx="24" cy="55" r="4" fill="#F5B58F" opacity=".6"/>
            <circle cx="57" cy="53" r="4" fill="#F5B58F" opacity=".6"/>
            <path d="M32 62 Q41 54 50 60"
                  fill="none" stroke="#493126" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </main>
</body>
</html>