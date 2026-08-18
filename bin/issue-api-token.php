<?php
declare(strict_types=1);

use App\Core\Database;
use App\Repositories\ApiClientRepository;
use App\Services\ApiClientCredentialService;
use App\Services\JwtService;
use App\Services\KeyDerivationService;

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}require dirname(__DIR__).'/bootstrap/app.php';
if(count($argv)!==4||filter_var($argv[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false||filter_var($argv[3],FILTER_VALIDATE_INT,['options'=>['min_range'=>60,'max_range'=>3600]])===false){fwrite(STDERR,"Uso: PINEPET_API_CLIENT_SECRET=<segredo> php bin/issue-api-token.php <client_id> <id_estabelecimento> <ttl_segundos>\n");exit(2);}
$clientId=$argv[1];$businessId=(int)$argv[2];$secret=(string)getenv('PINEPET_API_CLIENT_SECRET');putenv('PINEPET_API_CLIENT_SECRET');$ttl=(int)$argv[3];$database=new Database(require BASE_PATH.'/config/database.php');$client=(new ApiClientRepository($database))->active($clientId,$businessId);$credentials=new ApiClientCredentialService(new KeyDerivationService());
if(!$client||!$credentials->authenticate($secret,$client)){fwrite(STDERR,"Credenciais de cliente invalidas.\n");exit(3);}
$raw=trim((string)$client['scopes'],'{}');$scopes=$raw===''?[]:str_getcsv($raw,',','"','\\');$token=(new JwtService())->issue($clientId,$businessId,$scopes,$credentials->signingKey($client),(int)$client['segredo_versao'],$ttl);fwrite(STDOUT,$token.PHP_EOL);
