<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Core\Concern\ExceptionTrait;
use Salient\Core\Contract\ExceptionInterface;

/**
 * @api
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    use ExceptionTrait;
}
