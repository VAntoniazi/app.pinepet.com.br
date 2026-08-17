<?php
declare(strict_types=1);
$router->get('/',fn()=>App\Core\Response::redirect(App\Core\Auth::user()?'/painel':'/entrar',302));
$router->get('/entrar',[$auth,'showLogin']); $router->post('/entrar',[$auth,'login']); $router->post('/sair',[$auth,'logout']);
$router->get('/definir-senha',[$auth,'showActivation']); $router->post('/definir-senha',[$auth,'activate']);
$router->get('/primeiro-acesso',[$onboarding,'show']); $router->post('/primeiro-acesso',[$onboarding,'store']);
$router->get('/painel',[$dashboard,'index']);
