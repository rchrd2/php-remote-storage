<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage\Http;

use fkooman\RemoteStorage\Http\Exception\HttpException;

class Request
{
    /** @var array */
    private $serverData;

    /** @var array */
    private $getData;

    /** @var array */
    private $postData;

    /** @var string|null */
    private $rawData;

    public function __construct(array $serverData, array $getData = [], array $postData = [], $rawData = null)
    {
        $requiredHeaders = [
            'REQUEST_METHOD',
            'SERVER_NAME',
            'SERVER_PORT',
            'REQUEST_URI',
            'SCRIPT_NAME',
        ];

        foreach ($requiredHeaders as $key) {
            if (!array_key_exists($key, $serverData)) {
                // this indicates something wrong with the interaction between
                // the web server and PHP, these headers MUST always be available
                throw new HttpException(sprintf('missing header "%s"', $key), 500);
            }
        }
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
        $this->rawData = $rawData;
    }

    public function __toString()
    {
        return var_export($this->serverData, true);
    }

    public function getAuthority()
    {
        // scheme
        if (!array_key_exists('REQUEST_SCHEME', $this->serverData)) {
            $requestScheme = 'http';
        } else {
            $requestScheme = $this->serverData['REQUEST_SCHEME'];
        }
        $requestScheme = 'https';

        // server_name
        $serverName = $this->serverData['SERVER_NAME'];

        // port
        $serverPort = (int) $this->serverData['SERVER_PORT'];
        //var_dump([$requestScheme, $serverPort]);exit;
        $usePort = false;
        // if ('https' === $requestScheme && 443 !== $serverPort) {
        //     $usePort = true;
        // }
        if ('http' === $requestScheme && 80 !== $serverPort) {
            $usePort = true;
        }

        if ($usePort) {
            return sprintf('%s://%s:%d', $requestScheme, $serverName, $serverPort);
        }

        return sprintf('%s://%s', $requestScheme, $serverName);
    }

    public function getUri()
    {
        $requestUri = $this->serverData['REQUEST_URI'];

        return sprintf('%s%s', $this->getAuthority(), $requestUri);
    }

    public function getRoot()
    {
        $rootDir = dirname($this->serverData['SCRIPT_NAME']);
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    public function getRootUri()
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    public function getRequestMethod()
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    public function getServerName()
    {
        return $this->serverData['SERVER_NAME'];
    }

    public function isBrowser()
    {
        if (!array_key_exists('HTTP_ACCEPT', $this->serverData)) {
            return false;
        }

        return false !== mb_strpos($this->serverData['HTTP_ACCEPT'], 'text/html');
    }

    public function getPathInfo()
    {
        // remove the query string
        $requestUri = $this->serverData['REQUEST_URI'];
        if (false !== $pos = mb_strpos($requestUri, '?')) {
            $requestUri = mb_substr($requestUri, 0, $pos);
        }

        // remove script_name (if it is part of request_uri
        if (0 === mb_strpos($requestUri, $this->serverData['SCRIPT_NAME'])) {
            return substr($requestUri, mb_strlen($this->serverData['SCRIPT_NAME']));
        }

        // remove the root
        if ('/' !== $this->getRoot()) {
            return mb_substr($requestUri, mb_strlen($this->getRoot()) - 1);
        }

        return $requestUri;
    }

    public function getQueryParameter($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->getData, $key, $isRequired, $defaultValue);
    }

    public function getPostParameter($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->postData, $key, $isRequired, $defaultValue);
    }

    public function getHeader($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->serverData, $key, $isRequired, $defaultValue);
    }

    public function getBody()
    {
        return $this->rawData;
    }
}
