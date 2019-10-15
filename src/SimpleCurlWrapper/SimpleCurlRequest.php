<?php
/**
 * Created by PhpStorm.
 * User: gorelov
 * Date: 2019-10-10
 * Time: 16:12
 */

namespace SimpleCurlWrapper;

/**
 * Class SimpleCurlRequest
 * @package SimpleCurlWrapper
 */
class SimpleCurlRequest
{
    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT  = 'PUT';

    /**
     * @var bool|string
     */
    private $url = false;

    /**
     * @var string
     */
    private $method = 'GET';

    /**
     * @var array|null
     */
    private $data = null;

    /**
     * @var array|null
     */
    private $headers = null;

    /**
     * @var array|null
     */
    private $options = null;

    /**
     * @var callback|null
     */
    private $callback = null;

    /**
     * @param string|bool   $url      Url for load
     * @param string        $method   Method: METHOD_GET, METHOD_POST, METHOD_PUT or custom
     * @param array|null    $data     Data, will append to url if method = GET, or will put to body
     * @param array|null    $headers  Array of headers, for example: array('User-Agent: test', 'Accept: json')
     * @param array|null    $options  Array of curl options, for example: array(CURLOPT_COOKIE => 'test=1')
     * @param callback|null $callback Callback function, will called after request loaded
     */
    public function __construct($url = false, $method = self::METHOD_GET, $data = null, $headers = null, $options = null, $callback = null)
    {
        $this->url      = $url;
        $this->callback = $callback;
        $this->method   = $method;
        $this->data     = $data;
        $this->headers  = $headers;
        $this->options  = $options;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        unset($this->url, $this->method, $this->data, $this->headers, $this->options, $this->callback);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'url'      => $this->url,
            'method'   => $this->method,
            'data'     => $this->data,
            'headers'  => $this->headers,
            'options'  => $this->options,
            'callback' => (is_array($this->callback) && isset($this->callback[1]) && is_string($this->callback[1])) ? $this->callback[1] : '',
        );
    }

    /**
     * @return bool|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param bool|string $url
     * @return SimpleCurlRequest
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return SimpleCurlRequest
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array|null $data
     * @return SimpleCurlRequest
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array|null $headers
     * @return SimpleCurlRequest
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array|null $options
     * @return SimpleCurlRequest
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param callable|null $callback
     * @return SimpleCurlRequest
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;

        return $this;
    }
}
