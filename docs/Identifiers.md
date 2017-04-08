# Identifiers

Identifiers will identify an user or service based on the information that was extracted from the request by the authenticators.

## Password

The password identifier checks the passed credentials against a datasource.

Configuration options:

* **fields**: The fields for the lookup. Default is `['username' => 'username', 'password' => 'password']`.
  You can also set the `username` to an array. For e.g. using
  `['username' => ['username', 'email'], 'password' => 'password']` will allow
  you to match value of either username or email columns.
* **resolver**: The identity resolver. Default is `Authentication.Orm` which uses CakePHP ORM.
* **passwordHasher**: Password hasher. Default is `DefaultPasswordHasher::class`.

## Token

Checks the passed token against a datasource.

Configuration options:

* **tokenField**: The field in the database to check against. Default is `token`.
* **dataField**: The field in the passed data from the authenticator. Default is `token`.
* **resolver**: The identity resolver. Default is `Authentication.Orm` which uses CakePHP ORM.

## JWT Subject

Checks the passed JWT token against a datasource.

* **tokenField**: The field in the database to check against. Default is `id`.
* **dataField**: The payload key to get user identifier from. Default is `sub`.
* **resolver**: The identity resolver. Default is `Authentication.Orm` which uses CakePHP ORM.

## Callback

Allows you to use a callback for identification. This is useful for simple identifiers or quick prototyping.

Configuration options:

* **callback**: Default is `null` and will cause an exception. You're required to pass a valid callback to this option to use the authenticator.

# Identifier resolvers

Identifier resolver is an adapter for a datasource. It is used to find an identity in a database or any other data store.

## ORM Resolver

Identity resolver for CakePHP ORM.

Configuration options:

* **userModel**: The user model. Default is `Users` and all pages will be checked.
* **finder**: The finder to use with the model. Default is `all`.

In order to use ORM resolver you must require `cakephp/orm` in your `composer.json` file.

## Writing your own resolver

Authentication plugin can work with any ORM or datasource. All you need is to write your own resolver.
Resolvers must implement `Authentication\Identifier\Resolver\ResolverInterface` and should reside under `App\Identifier\Resolver` namespace.

Resolver can be configured using `resolver` config option:

```php
$service->loadIdentifier('Authentication.Password', [
    'resolver' => 'Custom' //or full class name: \Some\Other\Custom\Resolver::class
]);
```

Or injected using a setter:

```php
$resolver = new \App\Identifier\Resolver\CustomResolver();
$identifier = $service->loadIdentifier('Authentication.Password');
$identifier->setResolver($resolver);
```
