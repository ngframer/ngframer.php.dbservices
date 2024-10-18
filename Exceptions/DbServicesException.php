<?php

namespace NGFramer\NGFramerPHPDbServices\exceptions;

use NGFramer\NGFramerPHPExceptions\exceptions\_BaseException;
use Throwable;
class DbServicesException extends _BaseException
{
    public function __construct($message = null, int $code = 0, ?Throwable $previous = null, int $statusCode = 500, array $details = [])
    {
        parent::__construct($message, $code, $previous, $statusCode, $details);
    }
}