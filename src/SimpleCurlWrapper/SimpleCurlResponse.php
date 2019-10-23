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
     * @var string
     */
    private $bodyPath = '';

    /**
     * @var string
     */
    private $infoPath = '';

    /**
     * @var string
     */
    private $headersPath = '';

    /**
     * @var string
     */
    private $requestPath = '';

    /**
     * @var string
     */
    private $tempPath = '';

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
        $pid = getmypid();
        $loaderTempDir = sys_get_temp_dir().'/_loader_/';
        $this->tempPath = $loaderTempDir.$pid.'/';
        if (!file_exists($loaderTempDir)) {
            mkdir($loaderTempDir);
        }
        if (!file_exists($this->tempPath)) {
            mkdir($this->tempPath);
        }

        $requestSerialized = serialize($request);
        $requestHash = sha1($requestSerialized.microtime(true).microtime(false).rand(0, 999999));
        $this->bodyPath = $this->tempPath.'_bod_'.$requestHash;
        $this->infoPath = $this->tempPath.'_inf_'.$requestHash;
        $this->headersPath = $this->tempPath.'_hdr_'.$requestHash;
        $this->requestPath = $this->tempPath.'_req_'.$requestHash;

        file_put_contents($this->bodyPath, $body);
        file_put_contents($this->infoPath, serialize($info));
        file_put_contents($this->headersPath, $headers);
        file_put_contents($this->requestPath, $requestSerialized);

        $body    = null;
        $info    = null;
        $headers = null;
        $request = null;

        $this->headers = '';
        $this->body    = '';
        $this->info    = array();
        $this->request = null;

        register_shutdown_function(array($this, '__destruct'));
    }

    /**
     * @return string
     */
    public function &getHeaders()
    {
        $headers = file_get_contents($this->headersPath);

        return $headers;
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
        $body = file_get_contents($this->bodyPath);

        return $body;
    }

    /**
     * @return string
     */
    public function &getBodyAsJson()
    {
        $json = json_decode($this->getBody(), true);

        return $json;
    }

    /**
     * @return array
     */
    public function &getInfo()
    {
        $info = unserialize(file_get_contents($this->infoPath));

        return $info;
    }

    /**
     * @return SimpleCurlRequest
     */
    public function &getRequest()
    {
        $request = unserialize(file_get_contents($this->requestPath));

        return $request;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        @unlink($this->bodyPath);
        @unlink($this->headersPath);
        @unlink($this->requestPath);
        @unlink($this->infoPath);
    }
}
