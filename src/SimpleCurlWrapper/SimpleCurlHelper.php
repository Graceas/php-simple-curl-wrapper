<?php
/**
 * Created by PhpStorm.
 * User: gorelov
 * Date: 2019-10-10
 * Time: 16:25
 */

namespace SimpleCurlWrapper;

/**
 * Class SimpleCurlHelper
 * @package Scraper\ScraperToolsBundle\Lib
 */
class SimpleCurlHelper
{
    /**
     * Helper function to set up a new request by setting the appropriate options
     *
     * @param SimpleCurlRequest $request        Request
     * @param array             $defaultOptions Default options
     * @param array             $defaultHeaders Default headers
     *
     * @return array
     */
    public static function getOptions(SimpleCurlRequest $request, $defaultOptions = array(), $defaultHeaders = array())
    {
        // options for this entire curl object
        $options = $defaultOptions;
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            if (!isset($options[CURLOPT_FOLLOWLOCATION])) {
                $options[CURLOPT_FOLLOWLOCATION] = 1;
            }
            if (!isset($options[CURLOPT_MAXREDIRS])) {
                $options[CURLOPT_MAXREDIRS] = 5;
            }
        }

        $headers = $defaultHeaders;

        // append custom options for this specific request
        if ($request->getOptions()) {
            $options = $request->getOptions() + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $request->getUrl();

        // posting data w/ this request?;
        if ($request->getData()) {
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
            $options[CURLOPT_POSTFIELDS] = (is_array($request->getData()) || is_object($request->getData())) ? http_build_query($request->getData()) : $request->getData();
        }

        if ($request->getHeaders()) {
            $headers = array_merge($headers, $request->getHeaders());
        }
        if ($headers) {
            if (!isset($options[CURLOPT_HEADER])) {
                $options[CURLOPT_HEADER] = 0;
            }
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        unset($headers);

        return $options;
    }
}
