<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

/**
 * MyTrait
 */
trait MyTrait
{
    use MyBaseTrait;

    /**
     * MyTrait::$MyDocumentedProperty PHPDoc
     */
    public $MyDocumentedProperty;

    /**
     * MyTrait::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod() {}
}