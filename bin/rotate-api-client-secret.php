<?php
declare(strict_types=1);

use App\Core\Database;
use App\Repositories\ApiClientRepository;
use App\Services\ApiClientCredentialService;
use App\Services\KeyDerivationService;

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}require dirname(__DIR__).'/bootstrap/app.php';
if(count($argv)!==3||filter_var($argv[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false){fwrite(STDERR,"Uso: php bin/rotate-api-client-secret.php <client_id> <id_estabelecimento>\n");exit(2);}
$clientId=$argv[1];$businessId=(int)$argv[2];$database=new Database(require BASE_PATH.'/config/database.php');$repository=new ApiClientRepository($database);if(!$repository->active($clientId,$businessId)){fwrite(STDERR,"Cliente inexistente, inativo ou expirado.\n");exit(3);}
$generated=(new ApiClientCredentialService(new KeyDerivationService()))->generate($clientId,$businessId);if(!$repository->storeCredentials($clientId,$businessId,$generated['verifier'],$generated['encrypted_key'])){fwrite(STDERR,"Nao foi possivel atualizar o cliente.\n");exit(4);}
fwrite(STDOUT,"CLIENT_SECRET (exibido uma unica vez): ".$generated['secret'].PHP_EOL);
