<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bidang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BidangController extends Controller
{
    // Menampilkan daftar bidang
    public function index()
    {
        $bidangs = Bidang::orderBy('nama_bidang')->get();

        return view('admin.bidang.index', compact('bidangs'));
    }

    // Menampilkan form tambah bidang
    public function create()
    {
        return view('admin.bidang.create');
    }

    // Menyimpan bidang baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bidang' => [
                'required',
                'string',
                'max:255',
                'unique:bidang,nama_bidang',
            ],
        ], [
            'nama_bidang.required' => 'Nama bidang wajib diisi.',
            'nama_bidang.string' => 'Nama bidang harus berupa teks.',
            'nama_bidang.max' => 'Nama bidang maksimal 255 karakter.',
            'nama_bidang.unique' => 'Nama bidang tersebut sudah tersedia.',
        ]);

        Bidang::create($validated);

        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Data berhasil disimpan.');
    }
}
