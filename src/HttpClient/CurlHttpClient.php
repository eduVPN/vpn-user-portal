<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\HttpClient;

use RuntimeException;

class CurlHttpClient implements HttpClientInterface
{
    /** @var resource */
    private $curlChannel;

    /** @var bool */
    private $httpsOnly = true;

    public function __construct(array $configData = [])
    {
        if (false === $this->curlChannel = \curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }
        if (\array_key_exists('httpsOnly', $configData)) {
            $this->httpsOnly = (bool) $configData['httpsOnly'];
        }
    }

    public function __destruct()
    {
        \curl_close($this->curlChannel);
    }

    /**
     * @param string               $requestUri
     * @param array<string,string> $requestHeaders
     *
     * @return Response
     */
    public function get($requestUri, array $requestHeaders = [])
    {
        return $this->exec(
            [
                CURLOPT_URL => $requestUri,
            ],
            $requestHeaders
        );
    }

    /**
     * @param array                $curlOptions
     * @param array<string,string> $requestHeaders
     *
     * @return Response
     */
    private function exec(array $curlOptions, array $requestHeaders)
    {
        // reset all cURL options
        $this->curlReset();

        $headerList = [];

        $defaultCurlOptions = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_PROTOCOLS => $this->httpsOnly ? CURLPROTO_HTTPS : CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION => /**
             * @param resource $curlChannel
             * @param string   $headerData
             *
             * @return int
             */
            function ($curlChannel, $headerData) use (&$headerList) {
                if (false !== \strpos($headerData, ':')) {
                    list($key, $value) = \explode(':', $headerData, 2);
                    $headerList[\trim($key)] = \trim($value);
                }

                return \strlen($headerData);
            },
        ];

        if (0 !== \count($requestHeaders)) {
            $curlRequestHeaders = [];
            foreach ($requestHeaders as $k => $v) {
                $curlRequestHeaders[] = \sprintf('%s: %s', $k, $v);
            }
            $defaultCurlOptions[CURLOPT_HTTPHEADER] = $curlRequestHeaders;
        }

        if (false === \curl_setopt_array($this->curlChannel, $curlOptions + $defaultCurlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        $responseData = \curl_exec($this->curlChannel);
        if (!\is_string($responseData)) {
            $curlError = \curl_error($this->curlChannel);
            throw new RuntimeException(\sprintf('failure performing the HTTP request: "%s"', $curlError));
        }

        return new Response(
            \curl_getinfo($this->curlChannel, CURLINFO_HTTP_CODE),
            $responseData,
            $headerList
        );
    }

    /**
     * @return void
     */
    private function curlReset()
    {
        // requires PHP >= 5.5 for curl_reset
        if (\function_exists('curl_reset')) {
            \curl_reset($this->curlChannel);

            return;
        }

        // reset the request method to GET, that is enough to allow for
        // multiple requests using the same cURL channel
        if (false === \curl_setopt_array(
            $this->curlChannel,
            [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [],
            ]
        )) {
            throw new RuntimeException('unable to set cURL options');
        }
    }
}
