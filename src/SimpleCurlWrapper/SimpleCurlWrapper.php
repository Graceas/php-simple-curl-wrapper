<?php
/**
 * Created by PhpStorm.
 * User: gorelov
 * Date: 2019-10-10
 * Time: 16:06
 */

namespace SimpleCurlWrapper;

use SimpleCurlWrapper\Exception\SimpleCurlException;

/**
 * Class that holds a rolling queue of curl requests.
 *
 * @throws SimpleCurlException
 */
class SimpleCurlWrapper
{
    /**
     * Window size is the max number of simultaneous connections allowed.
     *
     * REMEMBER TO RESPECT THE SERVERS:
     * Sending too many requests at one time can easily be perceived
     * as a DOS attack. Increase this windowSize if you are making requests
     * to multiple servers or have permission from the receiving server admins.
     *
     * @var int
     */
    private $windowSize = 5;

    /**
     * Timeout is the timeout used for curl_multi_select.
     *
     * @var float
     */
    private $timeout = 10;

    /**
     * Set your base options that you want to be used with EVERY request.
     *
     * @var array
     */
    protected $options = array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 60,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HEADER         => 1,
    );

    /**
     * @var array
     */
    private $headers = array();

    /**
     * The request queue
     *
     * @var array SimpleCurlRequest
     */
    private $requests = array();

    /**
     * The locked requests (already send)
     *
     * @var array SimpleCurlRequest
     */
    private $lockedRequests = array();

    /**
     * Maps handles to request indexes
     *
     * @var array SimpleCurlRequest
     */
    private $requestMap = array();

    /**
     * @var array
     */
    private $handlers = array();

    /**
     * @var resource
     */
    private $master;

    /**
     * @var bool
     */
    protected $immediatelyStop = false;

    /**
     * @var bool
     */
    protected $riseErrors = true;

    /**
     * @var int
     */
    protected $trafficIn = 0;

    /**
     * @var int
     */
    protected $trafficOut = 0;

    /**
     * @throws SimpleCurlException
     */
    public function __construct()
    {
        // checking if cURL enabled
        if (!function_exists('curl_init')) {
            throw new SimpleCurlException("CURL is not enabled");
        }
    }

    /**
     * Execute multiple requests
     *
     * @param int|null $windowSize Window size is the max number of simultaneous connections allowed (if null use default).
     *
     * @throws SimpleCurlException
     */
    public function execute($windowSize = null)
    {
        $this->doCurl($windowSize);
        // clear request
        $this->requests       = array();
        $this->lockedRequests = array();
        $this->requestMap     = array();
    }

    /**
     * Performs multiple curl requests
     *
     * @param int|null $windowSize Window size is the max number of simultaneous connections allowed (if null use default).
     *
     * @throws SimpleCurlException
     */
    private function doCurl($windowSize = null)
    {
        $this->immediatelyStop = false;
        if ($windowSize) {
            $this->windowSize = $windowSize;
        }

        // make sure the rolling window isn't greater than the # of urls
        $requestCount = sizeof($this->requests);
        if ($requestCount < $this->windowSize) {
            $this->windowSize = $requestCount;
        }

        // prepare handlers
        $this->handlers = array();

        for ($i = 0; $i < $this->windowSize; $i++) {
            $ch = curl_init();
            $this->handlers[$i] = array(
                'key'     => (string) $ch,
                'handler' => $ch,
            );
        }

        if ($this->windowSize == 0) {
            return;
        }

        // process requests
        $batchSize = ceil($requestCount / $this->windowSize);

        for ($i = 0; $i < $batchSize; $i++) {
            $handlersMap = array();
            $this->master   = curl_multi_init();

            // prepare requests
            for ($j = 0; $j < $this->windowSize; $j++) {
                curl_setopt($this->handlers[$j]['handler'], CURLOPT_HEADERFUNCTION, null);
                curl_setopt($this->handlers[$j]['handler'], CURLOPT_READFUNCTION, null);
                curl_setopt($this->handlers[$j]['handler'], CURLOPT_WRITEFUNCTION, null);
                curl_setopt($this->handlers[$j]['handler'], CURLOPT_PROGRESSFUNCTION, null);
                curl_reset($this->handlers[$j]['handler']);

                $key = $i * $this->windowSize + $j;
                $this->handlers[$j]['request_key'] = $key;
                $handlersMap[$this->handlers[$j]['key']] = $j;

                if ($key >= $requestCount) {
                    break;
                }

                $options = SimpleCurlHelper::getOptions($this->requests[$key], $this->options, $this->headers);
                curl_setopt_array($this->handlers[$j]['handler'], $options);
                curl_multi_add_handle($this->master, $this->handlers[$j]['handler']);
            }

            if ($this->immediatelyStop) {
                break;
            }

            // execute requests
            do {
                /** @noinspection PhpStatementHasEmptyBodyInspection */
                while (($run = curl_multi_exec($this->master, $running)) == CURLM_CALL_MULTI_PERFORM);

                if ($run != CURLM_OK) {
                    break;
                }

                // a request was just completed -- find out which one
                while ($done = curl_multi_info_read($this->master)) {
                    if ($this->immediatelyStop) {
                        break;
                    }

                    // get the info and content returned on the request
                    $info = curl_getinfo($done['handle']);
                    $this->trafficIn  += $info['size_download'];
                    $this->trafficOut += $info['request_size'];
                    $output = curl_multi_getcontent($done['handle']);

                    // send the return values to the callback function.
                    $key     = (string) $done['handle'];
                    $handler = $this->handlers[$handlersMap[$key]];
                    /** @var SimpleCurlRequest $request */
                    $request = $this->requests[$handler['request_key']];

                    $headers = substr($output, 0, $info['header_size']);
                    $body    = substr($output, $info['header_size']);

                    $info['requested_url'] = $request->getUrl();

                    if (is_callable($request->getCallback())) {
                        $callback = $request->getCallback();
                        $response = new SimpleCurlResponse($headers, $body, $info, $request);
                        call_user_func_array($callback, array(&$response));

                        $callback = null;
                        unset($callback);
                    }

                    $body     = null;
                    $headers  = null;
                    $output   = null;
                    $info     = null;
                    $request  = null;

                    unset($body, $headers, $output, $info, $request);

                    // remove the curl handle that just completed
                    curl_multi_remove_handle($this->master, $done['handle']);
                }

                // Block for data in / output; error handling is done by curl_multi_exec
                if ($running) {
                    curl_multi_select($this->master, $this->timeout);
                }

                if ($this->immediatelyStop) {
                    break;
                }
            } while ($running);

            // clear loaded requests
            foreach ($this->handlers as $handler) {
                $this->requests[$handler['request_key']] = null;
            }

            curl_multi_close($this->master);
        }

        // close all open handlers
        foreach ($this->handlers as &$handler) {
            @curl_close($handler['handler']);
            $handler = null;
        }

        $this->handlers = null;

//        curl_multi_close($this->master);

        $this->master = null;

        unset($running, $windowSize);
    }

    /**
     * Remove single request
     *
     * @param int $index
     *
     * @return boolean
     */
    public function removeRequest($index)
    {
        if (!isset($this->lockedRequests[$index])) {
            $this->requests[$index] = null;

            unset($this->requests[$index]);

            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if (is_array($this->handlers)) {
            foreach ($this->handlers as &$handler) {
                @curl_close($handler);
                $handler = null;
            }

            $this->handlers = null;
        }

        if ($this->master) {
            @curl_multi_close($this->master);

            $this->master = null;
        }

        $this->requests       = array();
        $this->lockedRequests = array();
        $this->requestMap     = array();

        unset($this->windowSize, $this->callback, $this->options, $this->headers, $this->requests, $this->requestMap, $this->lockedRequests);
    }

    /**
     * @return SimpleCurlRequest[]
     */
    public function &getRequests()
    {
        return $this->requests;
    }

    /**
     * @return int
     */
    public function getTrafficIn()
    {
        return $this->trafficIn;
    }

    /**
     * @return int
     */
    public function getTrafficOut()
    {
        return $this->trafficOut;
    }

    /**
     * @return SimpleCurlWrapper
     */
    public function resetTrafficIn()
    {
        $this->trafficIn = 0;

        return $this;
    }

    /**
     * @return SimpleCurlWrapper
     */
    public function resetTrafficOut()
    {
        $this->trafficOut = 0;

        return $this;
    }

    /**
     * @param bool $immediatelyStop
     * @return SimpleCurlWrapper
     */
    public function setImmediatelyStop($immediatelyStop)
    {
        $this->immediatelyStop = $immediatelyStop;

        return $this;
    }

    /**
     * @param bool $riseErrors
     * @return SimpleCurlWrapper
     */
    public function setRiseErrors($riseErrors)
    {
        $this->riseErrors = $riseErrors;

        return $this;
    }

    /**
     * @param float $timeout
     * @return SimpleCurlWrapper
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param array $options
     * @return SimpleCurlWrapper
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param $windowSize
     * @return SimpleCurlWrapper
     */
    public function setWindowSize($windowSize)
    {
        $this->windowSize = $windowSize;

        return $this;
    }

    /**
     * @param array $requests
     * @return SimpleCurlWrapper
     */
    public function setRequests($requests)
    {
        $this->requests = $requests;

        return $this;
    }

    /**
     * @param SimpleCurlRequest $request
     * @return SimpleCurlWrapper
     */
    public function addRequest(SimpleCurlRequest $request)
    {
        $this->requests[] = $request;

        return $this;
    }
}
