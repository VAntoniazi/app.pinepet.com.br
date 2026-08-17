<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\OnboardingController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Router;
use App\Repositories\AuthRepository;
use App\Repositories\ProfileRepository;

define('BASE_PATH',dirname(__DIR__));
$autoload=BASE_PATH.'/vendor/autoload.php';
if(is_file($autoload)) require $autoload; else spl_autoload_register(static function(string $class): void { $prefix='App\\'; if(str_starts_with($class,$prefix)){ $file=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; if(is_file($file)) require $file; }});
require BASE_PATH.'/bootstrap/helpers.php';
Env::load(BASE_PATH.'/.env');
date_default_timezone_set((string)env('APP_TIMEZONE','America/Sao_Paulo'));
ini_set('display_errors',env('APP_ENV','production')==='production'?'0':(env('APP_DEBUG',false)?'1':'0')); ini_set('log_errors','1'); error_reporting(E_ALL);
session_name((string)env('SESSION_NAME','pinepet_app')); ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); ini_set('session.cookie_httponly','1'); ini_set('session.cookie_samesite','Strict');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>env('APP_ENV','production')==='production'||env('SESSION_SECURE',true),'httponly'=>true,'samesite'=>'Strict']);
session_start();
$database=new Database(require BASE_PATH.'/config/database.php'); $auth=new AuthController(new AuthRepository($database)); $dashboard=new DashboardController(); $onboarding=new OnboardingController(new ProfileRepository($database));
$router=new Router(); require BASE_PATH.'/routes/web.php'; return $router;
