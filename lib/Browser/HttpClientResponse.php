<?php

namespace Browser;

class HttpClientResponse
{
    protected $url;
    protected $status;
    protected $headers;
    protected $content;
    protected $cookie;

    public function __construct($url, $status, $headers, $content, $cookie)
    {
        $this->url     = $url;
        $this->status  = $status;
        $this->headers = $headers;
        $this->content = $content;
        $this->cookie  = $cookie;
    }

    public function isStatusOk()
    {
        return (($this->status >= 200 && $this->status < 300) || $this->status == 304);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getCookie()
    {
        return $this->cookie;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getRedirectLocation()
    {
        if (!in_array($this->status, array(300, 301, 302, 303, 307))) {
            return null; // not redirect = no location
        }

        preg_match('/^Location:\s*(\S*)\s*$/m', $this->headers, $matches);
        if (!isset($matches[1])) {
            throw new \LogicException('Location header is not found in response');
        }
        return $matches[1];
    }

    public function isRedirectTo($location)
    {
        $redirect = $this->getRedirectLocation();
        if ($redirect === null) {
            return false;
        }
        return preg_match('#^(https?://[^/]+)?' . preg_quote($location, '#') . '$#', $redirect);
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getUrlPath()
    {
        return parse_url($this->url, PHP_URL_PATH);
    }
}

