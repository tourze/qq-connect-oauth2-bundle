<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

class QQOAuth2ApiException extends QQOAuth2Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $apiEndpoint = null,
        private ?array $apiResponse = null
    ) {
        parent::__construct($message, $code, $previous, [
            'api_endpoint' => $apiEndpoint,
            'api_response' => $apiResponse,
        ]);
    }

    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function getApiResponse(): ?array
    {
        return $this->apiResponse;
    }
}