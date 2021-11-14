<?php

namespace MyApp;

class MessageDB
{
    public static function addLog(Message $msg)
    {
        if (!LOG_TO_DB) return null;

        $query = "INSERT INTO `messages` (`login`, `message`, `ip`) VALUES ('{$msg->getAuthor()}', '{$msg->getMessage()}', '{$msg->getIp()}');";
        return self::callQuery($query);
    }

    public static function loadLast()
    {
        $query = "SELECT * FROM `messages` ORDER BY `id` ASC LIMIT " . LOAD_HISTORY_MESSAGES;
        return self::callQuery($query);
    }

    private static function callQuery(string $query)
    {
        try {
            return Db::getInstance()->query($query);
        } catch (\Throwable $e) {
            echo __METHOD__ . ": ERROR: {$e->getMessage()}" . PHP_EOL;
            return null;
        }
    }
}
