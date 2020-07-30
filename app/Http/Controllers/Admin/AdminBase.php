<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminBase extends Controller
{
    public function uploadFile(Request $request)
    {
        return view('welcome');
    }
}
