<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Facade\Cli;
use Lkrms\Facade\Console;

/**
 * Thrown when invalid command-line arguments are given
 *
 */
class InvalidCliArgumentException extends \Lkrms\Exception\Exception
{
    public function __construct(string $message = null)
    {
        if ($message)
        {
            Console::error(Cli::getProgramName() . ": $message");
        }

        parent::__construct($message ?: "Invalid arguments");
    }
}
