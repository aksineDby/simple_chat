<?php

namespace  MyApp;

class Message
{
    private $author;
    private $message;
    private $time;
    private $ip;

    public function __construct(string $author, string $message, string $ip, ?string $time = null)
    {
        $this->author = $author;
        $this->message = $message;
        $this->time = $time ?? time();
        $this->ip = $ip;
    }

    public function __toString()
    {
        return $this->getHtml();
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getHtml(): string
    {
        return "<span class='login'>{$this->getTime()}. <b>{$this->getAuthor()}</b>#{$this->getIp()}</span>: {$this->getMessage()}";
    }

    public static function loadMessages(): array
    {
        $messages = [];
        $res = MessageDB::loadLast();
        if (is_null($res)) return $messages;
        $rows = $res->fetchAll();
        foreach ($rows as $row) {
            echo __METHOD__ . $row['time'];
            $messages[] = new Message(
                (string)$row['login'],
                (string)$row['message'],
                (string)$row['ip'],
                (string)$row['time']
            );
        }
        return $messages;
    }
}
