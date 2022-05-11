<?php


namespace NetLinker\DelivererAgrip\Tests\Alghoritms;


use GuzzleHttp\Client;
use NetLinker\DelivererAgrip\Tests\TestCase;

class HttpClient extends TestCase
{
    public function testRun()
    {
        $client = new Client(['verify'=>false]);
        $response = $client->get('https://agrip.de/hiq-all-breed-adult-lamb-nowej-generacji-pelnowartosciowa-bezzbozowa-karma-dla-doroslych-psow-wszystkich-ras-z-jagniecina-9880#');
    }
}