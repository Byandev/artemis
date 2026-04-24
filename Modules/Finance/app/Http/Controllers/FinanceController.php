<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;

class FinanceController extends Controller
{
    public function index()
    {
        return redirect()->route('finance.dashboard');
    }
}
