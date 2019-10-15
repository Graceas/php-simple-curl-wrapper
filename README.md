SimpleCurlWrapper
====================

This simple CURL wrapper. Allows the processing of multiple Request's asynchronously.

Installation
============

Through composer:

    "require": {
        ...
        "graceas/php-simple-curl-wrapper": "v1.1"
        ...
    }

Usage
=====

    $requests = [
        (new \SimpleCurlWrapper\SimpleCurlRequest())
            ->setUrl('http://ip-api.com/json?r=1')
            ->setMethod(\SimpleCurlWrapper\SimpleCurlRequest::METHOD_GET)
            ->setHeaders([
                'Accept: application/json',
                'User-Agent: simple curl wrapper',
            ])
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => false,
            ])
            ->setCallback('loadCallback'),
        (new \SimpleCurlWrapper\SimpleCurlRequest())
            ->setUrl('http://ip-api.com/json?r=2')
            ->setMethod(\SimpleCurlWrapper\SimpleCurlRequest::METHOD_GET)
            ->setHeaders([
                'Accept: application/json',
                'User-Agent: simple curl wrapper',
            ])
            ->setOptions([
                CURLOPT_FOLLOWLOCATION => false,
            ])
            ->setCallback('loadCallback'),
    ];
    
    $wrapper = new \SimpleCurlWrapper\SimpleCurlWrapper();
    $wrapper->setRequests($requests);
    $wrapper->execute(2);
    
    function loadCallback(\SimpleCurlWrapper\SimpleCurlResponse $response) {
        print_r($response->getRequest()->getUrl());
        print_r($response->getHeadersAsArray());
        print_r($response->getBodyAsJson());
    }
