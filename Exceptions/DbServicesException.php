<?php

namespace NGFramer\NGFramerPHPDbServices\Exceptions;

use NGFramer\NGFramerPHPExceptions\exceptions\BaseException;
use Throwable;

class DbServicesException extends BaseException
{
    /**
     * Constructor function for DbServicesException.
     *
     * @param string|null $message
     * @param int $code
     * @param string $label
     * @param Throwable|null $previous
     * @param int $statusCode
     * @param array $details
     */
    public function __construct(string $message = null, int $code = 0, string $label = '', ?Throwable $previous = null, int $statusCode = 500, array $details = [])
    {
        parent::__construct($message, $code, $label, $previous, $statusCode, $details);
    }
}