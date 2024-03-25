Upgrading from 2.x to 3.x
#########################

.. code-block:: console

    composer require cakephp/authentication:^3.0 -W

or adjust your ``composer.json`` file manually and perform ``composer update -W``

Breaking changes
================

- Type declarations were added to all function parameter and returns where possible. These are intended
  to match the docblock annotations, but include fixes for incorrect annotations.
- Type declarations were added to all class properties where possible. These also include some fixes for
  incorrect annotations.
- ``\Authentication\Identifier\IdentifierInterface::CREDENTIAL_USERNAME`` was moved to ``\Authentication\Identifier\AbstractIdentifier::CREDENTIAL_USERNAME``.
- ``\Authentication\Identifier\IdentifierInterface::CREDENTIAL_PASSWORD`` was moved to ``\Authentication\Identifier\AbstractIdentifier::CREDENTIAL_PASSWORD``.
- ``\Authentication\Identifier\IdentifierInterface::CREDENTIAL_TOKEN`` was moved to ``\Authentication\Identifier\TokenIdentifier::CREDENTIAL_TOKEN``.
- ``\Authentication\Identifier\IdentifierInterface::CREDENTIAL_JWT_SUBJECT`` was moved to ``\Authentication\Identifier\JwtSubjectIdentifier::CREDENTIAL_JWT_SUBJECT``.
- ``AuthenticationMiddleware`` cannot be configured anymore. Configuration needs to happen on the ``AuthenticationService`` object.
