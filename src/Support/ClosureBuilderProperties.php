<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;

/**
 * How to instantiate an object or update an instance from a list of values
 *
 * @property-read array<string,int> $Parameters Property name => constructor parameter index
 * @property-read array<string,string> $Methods Property name => "magic" property method
 * @property-read array<string,string> $Properties Property name => declared property name
 * @property-read string[] $MetaProperties Arbitrary property names
 *
 * @see ClosureBuilder
 */
final class ClosureBuilderProperties implements IReadable
{
    use TFullyReadable;

    /**
     * Property name => constructor parameter index
     *
     * @var array<string,int>
     */
    protected $Parameters;

    /**
     * Property name => "magic" property method
     *
     * @var array<string,string>
     */
    protected $Methods;

    /**
     * Property name => declared property name
     *
     * @var array<string,string>
     */
    protected $Properties;

    /**
     * Arbitrary property names
     *
     * @var string[]
     */
    protected $MetaProperties;

    public function __construct(array $parameters, array $methods, array $properties, array $metaProperties)
    {
        $this->Parameters     = $parameters;
        $this->Methods        = $methods;
        $this->Properties     = $properties;
        $this->MetaProperties = $metaProperties;
    }
}