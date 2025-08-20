<?php

namespace Worddown\Utilities;

use Exception;
use Throwable;

/**
 * Class UnlinkException
 */
class UnlinkException extends Exception
{
    /**
     * Redefine the exception so message isn't optional
     *
     * @param string $message
     * @param int $code
     * @param ?\Throwable $previous
     */
    public function __construct($message, $code = 0, ?Throwable $previous = null)
    {
        /**
         * Make sure everything is assigned properly
         */
        parent::__construct($message, $code, $previous);
    }

    /**
     * Custom string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
