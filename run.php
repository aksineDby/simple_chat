<?php

if (php_sapi_name() !== 'cli') {
    exit('Sorry. Please use this as console script.');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Dependencies.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'configurations.php';

MyApp\WebsocketServer::start();
