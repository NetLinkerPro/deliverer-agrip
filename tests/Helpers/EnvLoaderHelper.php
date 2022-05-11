<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


use Dotenv\Dotenv;

trait EnvLoaderHelper
{

    /**
     * Load ENV from production or development orchestra
     */
    public static function loadEnvFromProductionOrDevelopmentOrchestra(){
        $fileEnv = Dotenv::create(__DIR__ . '/../../')->load();

        foreach ($fileEnv as $key=> $value){
            $_ENV[$key] = env($key) ?  env($key): $fileEnv[$key];
        }
    }
}