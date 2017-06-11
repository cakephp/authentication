# CakePHP Authentication

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/cakephp/authentication/master.svg?style=flat-square)](https://travis-ci.org/cakephp/authentication)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/authentication.svg?style=flat-square)](https://codecov.io/github/cakephp/authentication)

[PSR7](http://www.php-fig.org/psr/psr-7/) Middleware authentication stack for the CakePHP framework.

Don't know what a middleware is? [Check the CakePHP documentation](http://book.cakephp.org/3.0/en/controllers/middleware.html) and additionally [read this.](https://philsturgeon.uk/php/2016/05/31/why-care-about-php-middleware/)

This plugin is for CakePHP 3.4+.
If your application existed before (<= CakePHP 3.3), please make sure it is adjusted to leverage middleware as described in the [docs](http://book.cakephp.org/3.0/en/controllers/middleware.html#adding-the-new-http-stack-to-an-existing-application).

## Authentication is not Authorization

This plugin intentionally **does not** handle authorization. It was [decoupled](https://en.wikipedia.org/wiki/Coupling_(computer_programming)) from authorization on purpose for a clear [separation of concerns](https://en.wikipedia.org/wiki/Separation_of_concerns). See also [Computer access control](https://en.wikipedia.org/wiki/Computer_access_control). This plugin handles only  *identification* and *authentication*. We might have another plugin for authorization.

## Documentation

 * [Quick Start and Introduction to the basics](docs/Quick-start-and-introduction.md)
 * [Migration from the AuthComponent](docs/Migration-from-the-AuthComponent.md)
 * [Authenticators](docs/Authenticators.md)
 * [Identifiers](docs/Identifiers.md)
 * [Identity Objects](docs/Identity-Object.md)
