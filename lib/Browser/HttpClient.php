<?php

namespace Browser;

class HttpClient
{
    private $user;
    private $password;
    public function setBasicAuthorization($user, $password)
    {
        $this->user     = $user;
        $this->password = $password;
    }

    protected $cookieFile;
    public function setCookieFile($cookieFile)
    {
        $this->cookieFile = $cookieFile;
        $this->clearCookieFile();
    }
    public function clearCookieFile()
    {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }
    protected $baseUrl;
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    protected $followRedirect = false;
    public function setRedirectFollowing($follow)
    {
        $this->followRedirect = $follow;
    }

    public function request($method, $url, $type = null, $data = '')
    {
        $headers = array();

        switch ($type) {
            case null:
                break;
            case 'xml':
                $headers[] = 'Content-Type: application/xml';
                break;
            case 'json':
                $headers[] = 'Content-Type: application/json';
                if (is_array($data)) {
                    $data = json_encode($data);
                }
                break;
            case 'form':
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                if (is_array($data)) {
                    $data = http_build_query($data);
                }
                if ($method == 'GET') {
                    $url .= strpos($url, '?') !== false ? '&' . $data : '?' . $data;
                    $data = '';
                }
                break;
            default:
                throw new \Exception("Unexpected data type argument $type");
        }

        if (!preg_match('#^https?://#', $url)) {
            $url = $this->baseUrl . $url;
        }

        $ch = curl_init($url);

        switch ($method) {
            case 'OPTIONS':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_PUT, true);

                $fhPut = fopen('php://memory', 'rw');
                fwrite($fhPut, $data);
                rewind($fhPut);
                curl_setopt($ch, CURLOPT_INFILE, $fhPut);
                curl_setopt($ch, CURLOPT_INFILESIZE, strlen($data));
                //curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Expect: '));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                throw new \Exception("Unexpected method argument $method");
        }

        if ($this->user !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, urlencode($this->user) . ":" . urlencode($this->password));
        }

        if ($this->cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true); // exclude the header in the output
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // exec will return the response body
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followRedirect); // will follow redirects in response
        //curl_setopt($ch, CURLOPT_VERBOSE, true);

        $response = curl_exec($ch);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers    = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);
        if ($error) {
            curl_close($ch);
            throw new \Exception($error);
        }

        curl_close($ch);
        if (isset($fhPut)) {
            fclose($fhPut);
        }

        $cookie = ($this->cookieFile && file_exists($this->cookieFile)) ? file_get_contents($this->cookieFile) : '';

        return new HttpClientResponse($url, $status, $headers, $body, $cookie);
    }
}
