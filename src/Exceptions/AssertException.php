<?php

namespace Adsry\Exceptions;

use Exception;

class AssertException extends Exception
{
    /**
     * @param mixed  $assert
     * @param string $class
     *
     * @throws static
     */
    public static function assertInstanceOf($assert, $class)
    {
        if (!$assert instanceof $class) {
            throw new static(
                sprintf(
                    'The assert must be an instance of %s but got %s.',
                    $class,
                    is_object($assert) ? get_class($assert) : gettype($assert)
                )
            );
        }
    }
}
