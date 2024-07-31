<?php

namespace NGFramer\NGFramerPHPDbServices\exceptions;

use NGFramer\NGFramerPHPExceptions\exceptions\supportive\_BaseError;

class DbServicesError extends _BaseError
{
    /**
     * Converts the error into an exception.
     * @throws DbServicesException
     */
    public function convertToException($code, $message, string $file, int $line, array $context = []): void
    {
        // Throw the exception (DbServicesException).
        throw new DbServicesException($message, $code, null, 500, []);
    }
}