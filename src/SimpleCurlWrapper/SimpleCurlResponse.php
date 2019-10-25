<?php
/**
 * Created by PhpStorm.
 * User: gorelov
 * Date: 2019-10-10
 * Time: 16:12
 */

namespace SimpleCurlWrapper;

/**
 * Class SimpleCurlResponse
 * @package SimpleCurlWrapper
 */
class SimpleCurlResponse
{
    /**
     * @var string
     */
    private $headers = '';

    /**
     * @var string
     */
    private $body = '';

    /**
     * @var array
     */
    private $info = array();

    /**
     * @var SimpleCurlRequest
     */
    private $request = null;

    /**
     * Response constructor.
     *
     * @param string            $headers Headers response block
     * @param string            $body    Body response block
     * @param array             $info    Info
     * @param SimpleCurlRequest $request Initial request
     */
    public function __construct(&$headers, &$body, &$info, SimpleCurlRequest &$request)
    {
        $this->headers = $headers;
        $this->body    = $body;
        $this->info    = $info;
        $this->request = $request;

        $body          = null;
        $info          = null;
        $headers       = null;
        $request       = null;

        unset($body, $info, $headers, $request);
    }

    /**
     * @return string
     */
    public function &getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getHeadersAsArray()
    {
        return explode("\n", trim($this->getHeaders()));
    }

    /**
     * @return string
     */
    public function &getBody()
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getBodyAsJson()
    {
        return json_decode($this->getBody(), true);
    }

    /**
     * @return array
     */
    public function &getInfo()
    {
        return $this->info;
    }

    /**
     * @return SimpleCurlRequest
     */
    public function &getRequest()
    {
        return $this->request;
    }

}
