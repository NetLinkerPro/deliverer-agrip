<?php


namespace NetLinker\DelivererAgrip\Sections\Logger\Services;


use Illuminate\Support\Facades\Log;

class DelivererLogger
{

    /** @var array $listeners */
    private static $listeners = [];

    /**
     * Log
     *
     * @param string $message
     */
    public static function log(string $message){

        $message = self::buildMessage($message);
        foreach (self::$listeners as $listener){
            $listener($message);
        }
    }

    /**
     * Listen
     *
     * @param callable $callable
     */
    public static function listen(callable $callable){
        array_push(self::$listeners, $callable);
    }

    /**
     * Build message
     *
     * @param string $message
     * @return string
     */
    private static function buildMessage(string $message)
    {
        return sprintf('[%s] %s', now()->format('yy-m-d H:i:s'), $message);
    }
}