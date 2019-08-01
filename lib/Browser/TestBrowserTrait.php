<?php

namespace Browser;

use Symfony\Component\DomCrawler\Crawler;

trait TestBrowserTrait
{
    //
    // API
    //

    protected function loadPageBasicAuth($url, $username, $password, $status = null)
    {
        $this->httpClient->setBasicAuthorization($username, $password);
        $response = $this->loadPage($url, $status);
        $this->httpClient->setBasicAuthorization(null, null);
        return $response;
    }

    protected function loadPage($url, $status = null)
    {
        $this->curResponse = $this->httpClient->request('GET', $url);
        $this->ensureNormalHttpStatus(200, $status);
        return $this;
    }

    protected function putJson($url, $data, $status = null)
    {
        $this->curResponse = $this->httpClient->request('PUT', $url, 'json', $data);
        $this->ensureNormalHttpStatus(200, $status);
        return $this;
    }

    protected function postJson($url, $data, $status = null)
    {
        $this->curResponse = $this->httpClient->request('POST', $url, 'json', $data);
        $this->ensureNormalHttpStatus(201, $status);
        return $this;
    }

    protected function postFormData($url, array $data, $status = null)
    {
        $this->curResponse = $this->httpClient->request('POST', $url, 'form', $data);
        $this->ensureNormalHttpStatus(201, $status);
        return $this;
    }

    protected function ensureNormalHttpStatus($defaultStatus, $forcedStatus = null)
    {
        if ($forcedStatus !== null) {
            $this->ensureHttpStatus($forcedStatus);
        } else {
            // expecting a normal status
            $redirectLocation = $this->curResponse->getRedirectLocation();
            if ($redirectLocation === null) {
                $this->ensureHttpStatus($defaultStatus); // OK or Created for POST
            } else {
                $this->ensureHttpStatus(302); // Found
            }
        }
    }

    protected function goByLink($aText, $status = null)
    {
        $this->loadPage($this->getLinkUrl($this->curResponse, $aText), $status);
        return $this;
    }

    protected function submitForm($submitButtonText, array $valuesToFill = array(), array $getParams = array())
    {
        $form = $this->getForm($this->curResponse, $submitButtonText, $valuesToFill);

        if (count($getParams) == 0) {
            $uri = $form->getUri();
        } else {
            $formUri = $form->getUri();
            $uri = $formUri . (strpos($formUri, '?') === false ? '?' : '&') . http_build_query($getParams);
        }

        $this->curResponse = $this->httpClient->request($form->getMethod(), $uri, 'form', $form->getValues());

        return $this;
    }

    /*
     * Хелперы для тестов
     * Зачем:
     * Чтобы для тестов написать текстовое описание было так же легко, как коментарий
     * Чтобы могло быть одно описание на несколько асертов
     * Чтобы не писать одно и тоже описание в каждом асерте
     * Чтобы тесты были более выразительными, хотя бы потому, что в стандартных assert* методах, сообщение - это последний аргумент, который не очень читается
     */
    protected $currentCaseDescription;
    protected function describe($currentCaseDescription)
    {
        $this->currentCaseDescription = $currentCaseDescription;
        return $this;
    }

    protected function ensureResponse($condition)
    {
        if (!$condition) {
            $this->failWithStyle();
        }
        $this->assertTrue(true); // для счетчика ассертов
        return $this;
    }
    protected function ensureHttpStatus($status)
    {
        $this->ensureResponse($this->curResponse->getStatus() == $status);
        return $this;
    }

    protected function ensureHeadersContent($needle)
    {
        $this->ensureResponse(strpos($this->curResponse->getHeaders(), $needle) !== false);
        return $this;
    }

    protected function ensurePageContains($needle)
    {
        $this->ensureResponse(strpos($this->curResponse->getContent(), $needle) !== false);
        return $this;
    }

    protected function ensurePageDoesNotContain($needle)
    {
        $this->ensureResponse(strpos($this->curResponse->getContent(), $needle) === false);
        return $this;
    }

    protected function ensurePageWithoutTagsContains($needle)
    {
        $this->ensureResponse(strpos(strip_tags($this->curResponse->getContent()), $needle) !== false);
        return $this;
    }

    protected function ensurePageWithoutTagsDoesNotContain($needle)
    {
        $this->ensureResponse(strpos(strip_tags($this->curResponse->getContent()), $needle) === false);
        return $this;
    }

    protected function ensureRedirectTo($location)
    {
        $this->ensureResponse($this->curResponse->isRedirectTo($location));
        return $this;
    }

    protected function followRedirectTo($location)
    {
        $desc = $this->currentCaseDescription;
        $this
            ->describe("{$desc}\nХотим редирект на {$location}")
            ->ensureResponse($this->curResponse->isRedirectTo($location))
            ->describe("{$desc}\nТеперь открываем {$this->curResponse->getRedirectLocation()}")
            ->loadPage($this->curResponse->getRedirectLocation())
            ->describe($desc)
        ;
        return $this;
    }


    //
    // CONTRACT WITH PHPUNIT TEST CASE
    //

    abstract public function assertTrue($condition, $message = '');
    abstract public function fail($message = '');


    //
    // IMPLEMENTATION
    //

    /** @var HttpClient */
    protected $httpClient;

    public function prepareHttpClient($baseUrl, $cookieFile = null)
    {
        $this->httpClient = new HttpClient();
        if ($cookieFile !== null) {
            $this->httpClient->setCookieFile($cookieFile);
        }
        $this->httpClient->setBaseUrl($baseUrl);
    }

    /** @var HttpClientResponse */
    protected $curResponse;

    protected function getLinkUrl(HttpClientResponse $response, $aText)
    {
        $linkCrawler = (new Crawler($response->getContent(), $response->getUrl()))
            ->selectLink($aText)
        ;
        if ($linkCrawler->count() == 0) {
            $this->failWithStyle("Не удалось найти ссылку '{$aText}'");
        }

        return $linkCrawler->link()->getUri();
    }

    protected function getForm(HttpClientResponse $response, $submitButtonText, array $valuesToFill = array())
    {
        $buttonCrawler = (new Crawler($response->getContent(), $response->getUrl()))
            ->selectButton($submitButtonText)
        ;
        if ($buttonCrawler->count() == 0) {
            $this->failWithStyle("Не удалось найти кнопку '{$submitButtonText}'");
        }

        return $buttonCrawler->form($valuesToFill);
    }

    protected function failWithStyle($message = '')
    {
        if ($message != '') {
            $message .= "\n";
        }
        $redirectLocation = $this->curResponse->getRedirectLocation();

        $content = $this->curResponse->getContent();
        $json    = @json_decode($content, true);

        $this->fail(
            $this->currentCaseDescription . "\n"
            . $message
            . "Запрос: {$this->curResponse->getUrl()}\n"
            . "Статус: {$this->curResponse->getStatus()}\n"
            . ($redirectLocation ? "Редирект: {$redirectLocation}\n" : '')
            . "Контент:\n"
            . $content
            . ($json ? "\nJSON:\n" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
        );
    }
}
