<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebapiClients\Contracts;


interface WebapiClient
{
    /**
     * Send request
     *
     * @param string $body
     * @return string
     */
    public function request(string $body): string;

    /**
     * Get session key
     *
     * @return string
     */
    public function getSessionKey(): string;
}