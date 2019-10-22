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
    private $timeout = 60;

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
        // rolling curl window must always be greater than 1
        if (count($this->requests) == 1) {
            $this->sendSingleRequest($this->requests[0]);
        } else {
            // start the rolling curl. windowSize is the max number of simultaneous connections
            $this->doCurl($windowSize);
        }
        // clear request
        $this->requests = array();
        $this->lockedRequests = array();
        $this->requestMap = array();
    }

    /**
     * Execute single Request with current curl instance
     *
     * @param SimpleCurlRequest $request  Request
     *
     * @return SimpleCurlResponse
     */
    public function executeRequest(SimpleCurlRequest $request)
    {
        return $this->sendSingleRequest($request);
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
        if (sizeof($this->requests) < $this->windowSize) {
            $this->windowSize = sizeof($this->requests);
        }

        if ($this->windowSize < 2) {
            if ($this->riseErrors) {
                throw new SimpleCurlException("Window size must be greater than 1");
            }
        }

        $master = curl_multi_init();

        // start the first batch of requests
        for ($i = 0; $i < $this->windowSize; $i++) {
            $ch = curl_init();

            $options = SimpleCurlHelper::getOptions($this->requests[$i], $this->options, $this->headers);

            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);

            // Add to our request Maps
            $key = (string) $ch;
            $this->requestMap[$key] = $i;
            $this->lockedRequests[$i] = true;
        }

        do {
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while (($run = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM){};

            if ($run != CURLM_OK) {
                break;
            }

            // a request was just completed -- find out which one
            while ($done = curl_multi_info_read($master)) {
                if ($this->immediatelyStop) {
                    break;
                }

                // get the info and content returned on the request
                $info = curl_getinfo($done['handle']);
                $this->trafficIn  += $info['size_download'];
                $this->trafficOut += $info['size_upload'];
                $output = curl_multi_getcontent($done['handle']);

                // send the return values to the callback function.
                $key = (string) $done['handle'];
                /** @var SimpleCurlRequest $request */
                $request = $this->requests[$this->requestMap[$key]];

                $headers = substr($output, 0, $info['header_size']);
                $body = substr($output, $info['header_size']);

                $response = new SimpleCurlResponse($headers, $body, $info, $request);

                if (is_callable($request->getCallback())) {
                    call_user_func_array($request->getCallback(), array(&$response));
                }

                $this->requests[$this->requestMap[$key]] = null;
                unset($this->requests[$this->requestMap[$key]]);
                $this->requestMap[$key] = null;
                unset($this->requestMap[$key], $response, $info);

                // start a new request (it's important to do this before removing the old one)
                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
                    $ch = curl_init();

                    $options = SimpleCurlHelper::getOptions($this->requests[$i], $this->options, $this->headers);

                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);

                    // Add to our request Maps
                    $key = (string) $ch;
                    $this->requestMap[$key] = $i;
                    $this->lockedRequests[$i] = true;
                    $i++;

                    unset($options, $request, $key);
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);
            }

            // Block for data in / output; error handling is done by curl_multi_exec
            if ($running) {
                curl_multi_select($master, $this->timeout);
            }

            if ($this->immediatelyStop) {
                break;
            }
        } while ($running);
        curl_multi_close($master);
    }

    /**
     * Performs a single curl request
     *
     * @param SimpleCurlRequest $request
     *
     * @return SimpleCurlResponse
     */
    private function sendSingleRequest(SimpleCurlRequest $request)
    {
        $ch = curl_init();

        $options = SimpleCurlHelper::getOptions($request, $this->options, $this->headers);

        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);
        $info   = curl_getinfo($ch);
        $this->trafficIn  += $info['size_download'];
        $this->trafficOut += $info['size_upload'];

        $headers = substr($output, 0, $info['header_size']);
        $body    = substr($output, $info['header_size']);

        $response = new SimpleCurlResponse($headers, $body, $info, $request);

        // it's not necessary to set a callback for one-off requests
        if ($request->getCallback() && is_callable($request->getCallback())) {
            call_user_func($request->getCallback(), $response);
        }

        return $response;
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
