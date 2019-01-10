<?php
/**
 * LINK Mobility SMS Notifications
 *
 * Sends transactional SMS notifications through the LINK Mobility messaging
 * service.
 *
 * @package Linkmobility\Notifications\Gateway
 * @author Joseph Leedy <joseph@wagento.com>
 * @author Yair García Torres <yair.garcia@wagento.com>
 * @copyright Copyright (c) LINK Mobility (https://www.linkmobility.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */
declare(strict_types=1);

namespace Linkmobility\Notifications\Gateway;

use GuzzleHttp\Exception\GuzzleException;
use Linkmobility\Notifications\Gateway\Entity\ResultInterface;
use Linkmobility\Notifications\Gateway\Factory\ClientFactory;
use Linkmobility\Notifications\Gateway\Factory\ResultFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * API Client
 *
 * @package Linkmobility\Notifications\Gateway
 * @author Joseph Leedy <joseph@wagento.com>
 */
final class ApiClient implements ApiClientInterface
{
    private const BASE_URL = 'https://wsx.sp247.net/sms/';

    /**
     * @var \Linkmobility\Notifications\Gateway\Factory\ClientFactory
     */
    private $clientFactory;
    /**
     * @var \Linkmobility\Notifications\Gateway\Factory\ResultFactory
     */
    private $resultFactory;
    /**
     * @var string
     */
    private $uri;
    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $password;
    /**
     * @var array
     */
    private $data;
    /**
     * @var array
     */
    private $headers = [];
    /**
     * @var string
     */
    private $httpMethod = self::HTTP_METHOD_GET;
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var \Linkmobility\Notifications\Gateway\Entity\ResultInterface
     */
    private $result;

    public function __construct(ClientFactory $clientFactory, ResultFactory $resultFactory)
    {
        $this->clientFactory = $clientFactory;
        $this->resultFactory = $resultFactory;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function setHttpMethod(string $httpMethod): void
    {
        if ($httpMethod !== self::HTTP_METHOD_GET && $httpMethod !== self::HTTP_METHOD_POST) {
            throw new \InvalidArgumentException("Method \"$httpMethod\" is not an allowed method.");
        }

        $this->httpMethod = $httpMethod;
    }

    /**
     * @throws \Linkmobility\Notifications\Gateway\ApiException
     */
    public function sendRequest(): void
    {
        if (!$this->isValid()) {
            throw new ApiException('The client was not properly configured. Errors: ' . \implode(', ', $this->errors));
        }

        $requestBody = \json_encode($this->data);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Could not encode request data as JSON. Error: ' . \json_last_error_msg());
        }

        try {
            $client = $this->clientFactory->create(['base_uri' => self::BASE_URL]);
            $response = $client->request(
                $this->httpMethod,
                self::BASE_URL . $this->uri,
                [
                    'headers' => \array_merge($this->getDefaultHeaders(), $this->headers, $this->getAuthenticationHeader()),
                    'body' => $requestBody
                ]
            );
        } catch (GuzzleException $e) {
            throw $this->getException($e);
        }

        $this->setResult($response, 'success');
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    private function isValid(): bool
    {
        $this->errors = [];

        if ($this->uri === null) {
            $this->errors[] = 'URI not provided';
        }

        if ($this->username === null) {
            $this->errors[] = 'API username not provided';
        }

        if ($this->password === null) {
            $this->errors[] = 'API password not provided';
        }

        if (empty($this->data)) {
            $this->errors[] = 'Request data is empty';
        }

        return !(\count($this->errors) > 0);
    }

    private function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    private function getAuthenticationHeader(): array
    {
        return [
            'Authorization' => 'Basic ' . \base64_encode($this->username . ':' . $this->password)
        ];
    }

    private function getException(GuzzleException $exception): ApiException
    {
        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $exception->getResponse();

        $this->setResult($response, 'error');

        return new ApiException(
            'An error occurred while making the API request. Error: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase(),
            0,
            null,
            $this->result->toArray()
        );
    }

    private function setResult(ResponseInterface $response, string $type): void
    {
        $responseBody = \json_decode((string)$response->getBody(), true);

        if ($responseBody === false) {
            return;
        }

        $this->result = $this->resultFactory->create($type, $responseBody);
    }
}