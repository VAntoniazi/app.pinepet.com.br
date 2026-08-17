<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;
use App\Core\View;
final class DashboardController { public function index(): string { return View::render('dashboard/index',['title'=>'Visão geral | PinePet','user'=>Auth::requireCompletedProfile()]); } }
