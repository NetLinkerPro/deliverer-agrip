<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Services\WebsiteClients\Contracts;

interface WebsiteClient
{
    /**
     * Get content
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function getContents(string $url, array $options = []): string;

    /**
     * Get content anonymous
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function getContentAnonymous(string $url, array $options = []): string;

    /**
     * Get content AJAX
     *
     * @param string $url
     * @param array $options
     * @param string $method
     * @return string
     */
    public function getContentAjax(string $url, array $options = [], string $method='POST'): string;
}