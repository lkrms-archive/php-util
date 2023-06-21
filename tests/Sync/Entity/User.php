<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * @generated by lk-util
 * @salient-generate-command generate sync entity --visibility=public --provider='Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint=/users --method=get 'Lkrms\Tests\Sync\Entity\User'
 */
class User extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Username;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var mixed[]|null
     */
    public $Address;

    /**
     * @var string|null
     */
    public $Phone;

    // Commented out for IExtensible testing

    ///**
    // * @var string|null
    // */
    //public $Website;

    /**
     * @var mixed[]|null
     */
    public $Company;

    /**
     * @var Post[]|null
     */
    public $Posts;
}
