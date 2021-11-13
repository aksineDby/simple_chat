<?php

namespace MyApp;

class WebsocketServer
{
    public static function start()
    {
        $server = stream_socket_server(WEBSOCKET_SERVER, $errorNumber, $errorString);

        if (!$server) {
            die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }

        $WebsocketHandler = new WebsocketHandler($server);
        $WebsocketHandler->start();
    }
}
