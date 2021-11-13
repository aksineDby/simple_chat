<?php

namespace MyApp;

class WebsocketHandler extends WebsocketWorker
{
    protected function onOpen($client, $info)
    {
        echo __METHOD__ . ": $client" . PHP_EOL;
        if (is_resource($client)){
            $this->connectionStore->attach(Connection::new($client));
            echo __METHOD__ . ": new count users: {$this->connectionStore->count()}" . PHP_EOL;
        }
    }

    protected function onClose($client)
    {
        echo __METHOD__ . ": $client" . PHP_EOL;
        $connection = Connection::new($client);
        if ($this->connectionStore->contains($connection)){
            $this->connectionStore->detach($connection);
        }
    }

    protected function onMessage($client, $data)
    {
        echo __METHOD__ . ": $client" . PHP_EOL;
        $data = $this->decode($data);

        echo __METHOD__ . ': ' . var_export($data,1). PHP_EOL;
        if (!$data['payload'] || !mb_check_encoding($data['payload'], 'utf-8')) {
            return;
        }
        $message = 'User #' . intval($client) . ' : ' . strip_tags($data['payload']);
        echo __METHOD__ . ": $message" . PHP_EOL;
        $this->send($message);
    }

    protected function send($data)
    {
        $data = $this->encode($data);

        $write = [];
        foreach ($this->connectionStore->getAll() as $client) {
            if ($client instanceof Connection) {
                $write[] = $client->getResource();
            }
        }

        if (stream_select($read, $write, $except, 0)) {
            foreach ($write as $client) {
                echo __METHOD__ . ": call fwrite to: $client" . PHP_EOL;
                @fwrite($client, $data);
            }
        }
    }
}
