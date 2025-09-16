<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set an array of trusted proxies. If you are running behind a proxy,
    | you may need to configure your proxy to forward the proper headers.
    |
    */

    'proxies' => '*', // Trust all proxies

    /*
    |--------------------------------------------------------------------------
    | Proxy Headers
    |--------------------------------------------------------------------------
    |
    | Change these if the proxy does not send the default header names.
    | Note that headers such as X-Forwarded-For are transformed to
    | HTTP_X_FORWARDED_FOR format.
    |
    */

    'headers' => [
        \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR => 'X_FORWARDED_FOR',
        \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST => 'X_FORWARDED_HOST',
        \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT => 'X_FORWARDED_PORT',
        \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
        \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB => 'X_FORWARDED_AWS_ELB',
    ],

];
