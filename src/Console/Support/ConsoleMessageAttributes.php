<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Concern\Immutable;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;

/**
 * Message attributes
 */
final class ConsoleMessageAttributes
{
    use Immutable;

    /**
     * Message level
     *
     * @var Level::*
     * @readonly
     */
    public int $Level;

    /**
     * Message type
     *
     * @var MessageType::*
     * @readonly
     */
    public int $Type;

    /**
     * True if the text is part 1 of a message
     *
     * @readonly
     */
    public bool $IsMsg1;

    /**
     * True if the text is part 2 of a message
     *
     * @readonly
     */
    public bool $IsMsg2;

    /**
     * True if the text is a message prefix
     *
     * @readonly
     */
    public bool $IsPrefix;

    /**
     * @param Level::* $level
     * @param MessageType::* $type
     */
    public function __construct(
        int $level,
        int $type,
        bool $isMsg1 = false,
        bool $isMsg2 = false,
        bool $isPrefix = false
    ) {
        $this->Level = $level;
        $this->Type = $type;
        $this->IsMsg1 = $isMsg1;
        $this->IsMsg2 = $isMsg2;
        $this->IsPrefix = $isPrefix;
    }

    /**
     * @return static
     */
    public function withIsMsg1(bool $value = true)
    {
        return $this->withPropertyValue('IsMsg1', $value);
    }

    /**
     * @return static
     */
    public function withIsMsg2(bool $value = true)
    {
        return $this->withPropertyValue('IsMsg2', $value);
    }

    /**
     * @return static
     */
    public function withIsPrefix(bool $value = true)
    {
        return $this->withPropertyValue('IsPrefix', $value);
    }
}
