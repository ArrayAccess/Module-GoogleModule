<?php
declare(strict_types=1);

namespace ArrayIterator\Service\Module\GoogleModule\Lib;

use ArrayIterator\Service\Core\Generator\DesktopUserAgent;
use ArrayIterator\Service\Core\Traits\Helper\StringHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Class VisionAPI
 * @package ArrayIterator\Service\Module\GoogleModule\Lib
 */
class VisionAPI
{
    use StringHelper;

    /**
     * @var string
     */
    protected $apiKey;
    protected $timeout = 60;
    // connection timed out
    protected $connectTimeout = 5;
    protected $explorerReferer = 'https://explorer.apis.google.com';
    protected $explorerOrigin = 'https://explorer.apis.google.com';
    protected $referer = 'https://content-vision.googleapis.com';
    protected $origin = 'https://content-vision.googleapis.com';
    protected $contentType = 'application/json';
    protected $userAgent;
    protected $client;

    //protected $visionUrl = 'https://vision.googleapis.com/v1/images:annotate';
    /**
     * @var string
     */
    protected $visionUrl = 'https://content-vision.googleapis.com/v1/images:annotate';
    protected $defaultRequestHeaders = [];

    /**
     * GoogleOCR constructor.
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->userAgent = (new DesktopUserAgent())->chrome();
        $this->defaultRequestHeaders = [
            'Referer' => $this->referer,
            // @todo new
            'Content-Type'  => $this->getContentType(),
            'X-Origin'      => $this->getExplorerOrigin(),
            'Origin'        => $this->getOrigin(),
            'X-Referer'     => $this->getExplorerReferer()
        ];

        $this->setApiKey($apiKey);
    }

    /**
     * @return string
     */
    public function getContentType() : string
    {
        return $this->contentType;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * @return string
     */
    public function getReferer(): string
    {
        return $this->referer;
    }

    /**
     * @return string
     */
    public function getExplorerReferer(): string
    {
        return $this->explorerReferer;
    }

    /**
     * @return string
     */
    public function getExplorerOrigin(): string
    {
        return $this->explorerOrigin;
    }

    /**
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @return string
     */
    public function getVisionUrl(): string
    {
        return $this->visionUrl;
    }

    /**
     * @return array
     */
    public function getDefaultRequestHeaders(): array
    {
        return $this->defaultRequestHeaders;
    }

    /**
     * @return string
     */
    public function getApiKey() : string
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return Client
     */
    public function getClient() : Client
    {
        if ($this->client) {
            return $this->client;
        }
        return $this->client = new Client([
            RequestOptions::TIMEOUT => $this->getTimeout(),
            RequestOptions::CONNECT_TIMEOUT => $this->getConnectTimeout(),
            RequestOptions::HEADERS => [
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control' => 'no-cache',
                'Language' => 'en-US,en;q=0.9,id;q=0.8,fr;q=0.7',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Request' => '1',
                'User-Agent' => $this->getUserAgent(),
            ],
            RequestOptions::FORCE_IP_RESOLVE => 'v4',
            RequestOptions::VERIFY => false,
            RequestOptions::COOKIES => true,
        ]);
    }

    /**
     * @param string $body
     * @param int $maxResult
     * @param array|null $context
     * @return string
     */
    public function createBody(
        string $body,
        $maxResult = 10,
        ?array $context = null
    ) : string {
        $body = base64_encode($body);
        $subContext = [
            'image' => [
                'content' => $body,
            ],
            'features' => [
                "type" => "TEXT_DETECTION",
                "maxResults" => $maxResult,
            ]
        ];
        unset($body);
        if (is_array($context) && !empty($context)) {
            $subContext['imageContext'] = $context;
        }

        $subContext = $this->createContext(
            $this->createSubContext($subContext)
        );

        return json_encode($subContext);
    }

    /**
     * @param array $context
     * @return array
     */
    public function createContext(array $context) : array
    {
        if (isset($context['requests'])) {
            if (!is_array($context['requests'])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid argument for context requests. Context Must be an array %s given.',
                        gettype($context['requests'])
                    )
                );
            }
            $context = $context['requests'];
        }

        if (!isset($context[0]) && isset($context['image'])
            && is_array($context['image'])
        ) {
            $context = $this->createSubContext($context);
        }

        if (!isset($context[0])
            || !is_array($context[0])
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid argument for sub context requests. Sub context Must be an array %s given.',
                    isset($context[0])
                        ? gettype($context[0])
                        : 'none'
                )
            );
        }

        return [
            'requests' => $context
        ];
    }

    /**
     * @param array $context
     * @return array
     */
    public function createSubContext(array $context) : array
    {
        return [
            $context,
        ];
    }

    /**
     * @param string|mixed $str
     * @return bool
     */
    public function isFile($str) : bool
    {
        return !is_string($str)
            || !$this->stringIsBinary($str)
            && !$this->stringIsHttpUrl($str)
            && strlen($str) < 256
            && preg_match('/^([A-Z]+\:)?[\/\\\]+[A-Z0-9]+/i', $str);
    }

    /**
     * @param string $url
     * @param int $maxResult
     * @param array|null $context
     * @return array|false
     */
    public function readFromUrl(
        string $url,
        int $maxResult = 10,
        array $context = null
    ) {
        if (!$this->stringIsHttpUrl($url)) {
            throw new InvalidArgumentException(
                'Arguments is not an url.'
            );
        }
        $client = $this->getClient();
        try {
            $headers = $client->getConfig(RequestOptions::HEADERS);
            $headers['Referer'] = preg_replace('/[^\/]+$/', '', explode('?', $url)[0]);
            $response = $client->get($url, [RequestOptions::HEADERS => $headers]);
            return $this->readFromBinary(
                (string) $response->getBody(),
                $maxResult,
                $context
            );
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $file
     * @param int $maxResult
     * @param array|null $context
     * @return array
     * @throws Throwable
     */
    public function readFromFile(string $file, int $maxResult = 10, array $context = null)
    {
        if (!$this->isFile($file)) {
            throw new InvalidArgumentException(
                'Arguments is not a file.'
            );
        }
        if (file_exists($file) && is_file($file)) {
            throw new InvalidArgumentException(
                sprintf('%s is not exists or not a file.', $file)
            );
        }

        clearstatcache(true);
        $socket = @fopen($file, 'r');
        if (!$socket) {
            throw new InvalidArgumentException(
                sprintf('Can not read %s.', $file)
            );
        }

        $stream = new Stream($socket);
        return $this->readFromBinary((string) $stream, $maxResult, $context);
    }

    /**
     * @param string|UploadedFileInterface $binary
     * @param int $maxResult
     * @param array|null $context
     * @return array|false
     * @throws Throwable
     */
    public function readFromBinary($binary, int $maxResult = 10, array $context = null)
    {
        if ($binary instanceof UploadedFileInterface) {
            $binary = (string) $binary->getStream();
        }
        if (!$this->stringIsBinary($binary)) {
            if (!$this->stringIsBase64($binary)) {
                throw new InvalidArgumentException(
                    'Argument image source is not a binary.'
                );
            }
            // check if base64
            $binary = \base64_decode($binary);
        }

        $info = getimagesizefromstring($binary, $imageInfo);
        if ($info === false) {
            throw new InvalidArgumentException(
                'Argument image source is not an image file.'
            );
        }

        $client = $this->getClient();
        $url = sprintf('%s?key=%s&alt=json', $this->visionUrl, $this->getApiKey());
        try {
            $config = $client->getConfig();
            $headers = $config[RequestOptions::HEADERS]??[];
            $headers = array_merge($headers, $this->defaultRequestHeaders);
            $config[RequestOptions::HEADERS] = $headers;
            $config[RequestOptions::DEBUG] = false;
            $config[RequestOptions::BODY] = $this->createBody($binary, $maxResult, $context);
            unset($binary, $headers); // freed
            $headers['Content-Length'] = strlen($config[RequestOptions::BODY]);
            $response = $client->post($url, $config);
            $body = json_decode((string) $response->getBody(), true);
            $response->getBody()->close();
            return $body;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * @param string $binary
     * @param int $maxResult
     * @param array|null $context
     * @return array[array, Client]
     * @throws Throwable
     */
    public function readImage(string $binary, int $maxResult = 10, array $context = null)
    {
        if ($this->stringIsHttpUrl($binary)) {
            return  $this->readFromUrl($binary, $maxResult, $context);
        } elseif ($this->isFile($binary)) {
            return  $this->readFromFile($binary, $maxResult, $context);
        }
        return $this->readFromBinary($binary, $maxResult, $context);
    }
}
