<?php

namespace MyApp;

use MyApp\Store\ConnectStore;

abstract class WebsocketWorker
{
    protected $server;
    protected $handshakes = [];
    protected $ips = [];
    protected $connectionStore;

    public function __construct($server)
    {
        $this->server = $server;
        $this->connectionStore = ConnectStore::getInstance();
    }

    public function start()
    {
        while (true) {
            //подготавливаем массив всех сокетов, которые нужно обработать
            $read = [];
            foreach ($this->connectionStore->getAll() as $connection) {
                if ($connection instanceof Connection) {
                    $read[] = $connection->getResource();
                }
            }
            $read[(int)$this->server] = $this->server;

            $write = [];
            if ($this->handshakes) {
                foreach ($this->handshakes as $clientId => $clientInfo) {
                    if ($clientInfo) {
                        $connection = $this->connectionStore->getById($clientId);
                        $write[] = $connection->getResource();
                    }
                }
            }

            stream_select($read, $write, $except, null);//обновляем массив сокетов, которые можно обработать

            if (in_array($this->server, $read)) { //на серверный сокет пришёл запрос от нового клиента
                //подключаемся к нему и делаем рукопожатие, согласно протоколу веб-сокета
                if ($client = stream_socket_accept($this->server, -1)) {
                    $address = explode(':', stream_socket_get_name($client, true));
                    if (isset($this->ips[$address[0]]) && $this->ips[$address[0]] > 2) {//блокируем более 2 соединений с одного ip
                        @fclose($client);
                    } else {
                        @$this->ips[$address[0]]++;

                        $connection = Connection::new($client);
                        $this->connectionStore->attach($connection);
                        $this->handshakes[$connection->getId()] = false;//отмечаем, что нужно сделать рукопожатие
                    }
                }

                //удаляем серверный сокет из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[(int)$this->server]);
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    if (isset($this->handshakes[intval($client)])) {
                        if ($this->handshakes[intval($client)]) {//если уже было получено рукопожатие от клиента
                            continue;//то до отправки ответа от сервера читать здесь пока ничего не надо
                        }

                        if (!$this->handshake($client)) {
                            $this->closeConnectionAndDeleteFromMemory(Connection::new($client));
                        }
                    } else {
                        $data = fread($client, 1000);

                        if (!strlen($data)) { //соединение было закрыто
                            $this->closeConnectionAndDeleteFromMemory(Connection::new($client));
                            $this->onClose($client);//вызываем пользовательский сценарий
                            continue;
                        }

//                        echo __METHOD__ . ': пришли данные от подключенных клиентов' . PHP_EOL;
                        $this->onMessage($client, $data);//вызываем пользовательский сценарий
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (!$this->handshakes[intval($client)]) {//если ещё не было получено рукопожатие от клиента
                        continue;//то отвечать ему рукопожатием ещё рано
                    }
                    $info = $this->handshake($client);
                    $this->onOpen($client, $info);//вызываем пользовательский сценарий
                }
            }
        }
    }

    private function closeConnectionAndDeleteFromMemory(Connection $connection)
    {
        unset($this->handshakes[$connection->getId()]);
        $address = explode(':', stream_socket_get_name($connection->getResource(), true));
        if (isset($this->ips[$address[0]]) && $this->ips[$address[0]] > 0) {
            @$this->ips[$address[0]]--;
        }
        $this->connectionStore->detach($connection);
    }

    protected function handshake($client)
    {
        $key = $this->handshakes[intval($client)];

        if (!$key) {
            //считываем заголовки из соединения
            $headers = fread($client, 10000);
            preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match);

            if (empty($match[1])) {
                return false;
            }

            $key = $match[1];

            $this->handshakes[intval($client)] = $key;
        } else {
            //отправляем заголовок согласно протоколу веб-сокета
            $SecWebSocketAccept = base64_encode(pack('H*', sha1($key . WEBSOCKET_ACCEPT_KEY)));
            $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept:$SecWebSocketAccept\r\n\r\n";
            fwrite($client, $upgrade);
            unset($this->handshakes[intval($client)]);
        }

        return $key;
    }

    protected function encode($payload, $type = 'text', $masked = false)
    {
        $frameHead = [];
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0
            if ($frameHead[2] > 127) {
                return ['type' => '', 'payload' => '', 'error' => 'frame too large (1004)'];
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = [];
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    protected function decode($data)
    {
        $unmaskedPayload = '';
        $decodedData = [];

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1');
        $payloadLength = ord($data[1]) & 127;

        // unmasked frame is received:
        if (!$isMasked) {
            return ['type' => '', 'payload' => '', 'error' => 'protocol error (1002)'];
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
                return ['type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)'];
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return false;
        }

        for ($i = $payloadOffset; $i < $dataLength; $i++) {
            $j = $i - $payloadOffset;
            if (isset($data[$i])) {
                $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
            }
        }
        $decodedData['payload'] = $unmaskedPayload;

        return $decodedData;
    }

    abstract protected function onOpen($client, $info);

    abstract protected function onClose($client);

    abstract protected function onMessage($client, $data);

    abstract protected function send($data);
}
