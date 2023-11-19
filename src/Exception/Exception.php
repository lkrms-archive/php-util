<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Exception\Concern\ExceptionTrait;
use Lkrms\Exception\Contract\ExceptionInterface;

/**
 * Base class for exceptions
 */
class Exception extends \Exception implements ExceptionInterface
{
    use ExceptionTrait;
}
