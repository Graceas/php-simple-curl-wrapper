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
     * @var string
     */
    private $bodyJson = -1;

    /**
     * @var array
     */
    private $info = array();

    /**
     * @var SimpleCurlRequest
     */
    private $request = null;

    /**
     * @var string
     */
    private $bodyPath = '';

    /**
     * @var boolean
     */
    private $bodyLoaded = false;

    /**
     * Response constructor.
     *
     * @param string            $headers Headers response block
     * @param string            $body    Body response block
     * @param array             $info    Info
     * @param SimpleCurlRequest $request Initial request
     */
    public function __construct($headers, $body, $info, SimpleCurlRequest $request)
    {
        $this->bodyPath = sys_get_temp_dir().'/_loader_'.sha1(serialize($request));
        file_put_contents($this->bodyPath, $body);
        unset($body);

        $this->headers = $headers;
        $this->body    = '';
        $this->info    = $info;
        $this->request = $request;
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
        return explode("\n", trim($this->headers));
    }

    /**
     * @return string
     */
    public function &getBody()
    {
        if ($this->bodyLoaded) {
            return $this->body;
        } else {
            $this->body = file_get_contents($this->bodyPath);

            return $this->body;
        }
    }

    /**
     * @return string
     */
    public function &getBodyAsJson()
    {
        if ($this->bodyJson !== -1) {
            return $this->bodyJson;
        }

        if (function_exists('json_decode')) {
            $this->bodyJson = json_decode($this->getBody(), true);
        }

        return $this->bodyJson;
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

    /**
     * Destructor
     */
    public function __destruct()
    {
        unlink($this->bodyPath);
    }
}
