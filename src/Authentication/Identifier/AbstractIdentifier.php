<?php
namespace Auth\Authentication\Identifier;

use Cake\Core\InstanceConfigTrait;

abstract class AbstractIdentifier implements IdentifierInterface {

    use InstanceConfigTrait;

    protected $_defaultConfig = [];

    public function __construct(array $config = [])
    {
        $this->config($config);
    }

}
