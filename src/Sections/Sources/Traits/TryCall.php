<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


use Exception;

trait TryCall
{

    /**
     * Try call
     *
     * @param callable $callable
     * @param int $tries
     * @param int $sleep
     * @return
     */
    public function tryCall(callable $callable, int $tries = 3, int $sleep=15){
        for ($i = 0 ; $i < $tries - 1 ; $i++){
            try {
                return $callable();
            } catch (Exception $e){
                sleep($sleep);
            }
        }
        return $callable();
    }
}