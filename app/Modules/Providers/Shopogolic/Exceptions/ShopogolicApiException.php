<?php

namespace App\Modules\Providers\Shopogolic\Exceptions;

use Exception;

class ShopogolicApiException extends Exception
{
    /**
     * @var int
     */
    protected int $statusCode;

    /**
     * @param string $message
     * @param int $statusCode
     * @param Exception|null $previous
     */
    public function __construct(string $message = "", int $statusCode = 0, ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
