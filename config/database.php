<?php
declare(strict_types=1);
return [
    'host'=>(string)env('DB_HOST',''), 'port'=>(int)env('DB_PORT',5432), 'database'=>(string)env('DB_NAME',''),
    'username'=>(string)env('DB_USER',''), 'password'=>(string)env('DB_PASSWORD',''), 'schema'=>(string)env('DB_SCHEMA','pinepet'),
    'sslmode'=>(string)env('DB_SSLMODE','prefer'), 'connect_timeout'=>max(1,(int)env('DB_CONNECT_TIMEOUT',8)),
];
