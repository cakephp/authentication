# CakePHP Authentication

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/cakephp/authentication/master.svg?style=flat-square)](https://travis-ci.org/cakephp/authentication)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/authentication.svg?style=flat-square)](https://codecov.io/github/cakephp/authentication)

[PSR7](https://www.php-fig.org/psr/psr-7/) Middleware authentication stack for the CakePHP framework.

Don't know what middleware is? [Check the CakePHP documentation](https://book.cakephp.org/3.0/en/controllers/middleware.html) and additionally [read this.](https://philsturgeon.uk/php/2016/05/31/why-care-about-php-middleware/)

## Authentication, not Authorization

This plugin intends to provide a framework around authentication and user
identification. Authorization is a [separate
concern](https://en.wikipedia.org/wiki/Separation_of_concerns) that has been
packaged into a separate [authorization plugin](https://github.com/cakephp/authorization).

## Installation

You can install this plugin into your CakePHP application using
[composer](https://getcomposer.org):

```
php composer.phar require cakephp/authentication
```

Load the plugin by adding the following statement in your project's
`src/Application.php`:
```php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Authentication');
}
```

## Documentation

Documentation for this plugin can be found in the [CakePHP
Cookbook](https://book.cakephp.org/authentication/2/en/)
