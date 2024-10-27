<?php

namespace NGFramer\NGFramerPHPDbServices\Exceptions;

use NGFramer\NGFramerPHPExceptions\exceptions\_BaseException;
use Throwable;

class DbServicesException extends _BaseException
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