<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;

// Panel principal: muestra el menú según el rol del usuario.
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', ['usuario' => Session::user()]);
    }
}
