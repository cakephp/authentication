<?php
namespace Auth\Authentication\Identifier;

interface IdentifierInterface {

    public function __construct(array $config = []);

    /**
     *
     */
    public function identify($data);

}
