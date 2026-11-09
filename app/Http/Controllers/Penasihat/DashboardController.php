<?php

namespace App\Http\Controllers\Penasihat;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('penasihat.dashboard');
    }
}