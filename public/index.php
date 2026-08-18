<?php
declare(strict_types=1);

use App\Core\ApiResponse;
use App\Core\HttpException;
use App\Core\Request;

$nonce=base64_encode(random_bytes(18));define('CSP_NONCE',$nonce);
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');header('Cross-Origin-Opener-Policy: same-origin');header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'; connect-src 'self'");header('Cache-Control: no-store, private');

$router=require dirname(__DIR__).'/bootstrap/app.php';
try{$request=Request::capture();$router->dispatch($request);}
catch(HttpException$e){$uri=(string)($_SERVER['REQUEST_URI']??'/');if(str_starts_with($uri,'/api/'))ApiResponse::error($e->apiCode,$e->getMessage(),$e->status,$e->details);App\Core\Response::abort($e->status,$e->getMessage());}
