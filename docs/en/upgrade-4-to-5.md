# Upgrade Guide 4.x to 5.x

Version 5.0 of the Authentication plugin adds support for CakePHP 6.
Application code that only uses the public API rarely needs to change, but
the plugin's classes follow the CakePHP 6 convention changes, so custom
subclasses need to be updated.

## Requirements

Version 5.x requires:

- PHP 8.4 or higher
- CakePHP 6.x, provided by `cakephp/http` and `cakephp/utility` 6.x
- PHPUnit 12.4 or higher when you run the plugin's tests

```bash
composer require cakephp/authentication:^5.0
```

Upgrade your application to CakePHP 6 first by following the
[CakePHP 6.0 Upgrade Guide](https://book.cakephp.org/6/en/appendices/6-0-upgrade-guide.html),
then install version 5 of this plugin.

## Breaking Changes

### Leading Underscore Removed From Properties

Following CakePHP 6, protected properties no longer start with an underscore.
Update any custom subclass that reads or writes these properties.

| Old (4.x) | New (5.x) |
| --------- | --------- |
| `$_defaultConfig` | `$defaultConfig` |
| `$_defaultOptions` | `$defaultOptions` |
| `$_identifier` | `$identifier` |
| `$_authentication` | `$authentication` |
| `$_authenticators` | `$authenticators` |
| `$_successfulAuthenticator` | `$successfulAuthenticator` |
| `$_result` | `$result` |
| `$_identity` | `$identity` |
| `$_passwordHasher` | `$passwordHasher` |
| `$_needsPasswordRehash` | `$needsPasswordRehash` |
| `$_hashers` | `$hashers` |
| `$_errors` | `$errors` |
| `$_data` | `$data` |
| `$_status` | `$status` |
| `$_connection` | `$connection` |
| `$_ldap` | `$ldap` |
| `$_loaded` | `$loaded` |

`$defaultConfig` is declared by the authenticators, identifiers, password
hashers, URL checkers, `AbstractCollection`, `AuthenticationService`,
`AuthenticationComponent`, `Identity` and `IdentityHelper`. The `$config`
property that comes from CakePHP's `InstanceConfigTrait` lost its underscore as
well.

**Before (4.x):**

```php
class CustomAuthenticator extends AbstractAuthenticator
{
    protected array $_defaultConfig = [
        'fields' => ['username' => 'email'],
    ];

    public function example(): void
    {
        $fields = $this->_defaultConfig['fields'];
    }
}
```

**After (5.x):**

```php
class CustomAuthenticator extends AbstractAuthenticator
{
    protected array $defaultConfig = [
        'fields' => ['username' => 'email'],
    ];

    public function example(): void
    {
        $fields = $this->defaultConfig['fields'];
    }
}
```

### Leading Underscore Removed From Methods

The same rename applies to the plugin's protected methods.

| Old (4.x) | New (5.x) | Class |
| --------- | --------- | ----- |
| `$_authenticateLegacyToken()` | `authenticateLegacyToken()` | `CookieAuthenticator` |
| `$_authenticateToken()` | `authenticateToken()` | `CookieAuthenticator` |
| `$_bindUser()` | `bindUser()` | `LdapIdentifier` |
| `$_buildLdapObject()` | `buildLdapObject()` | `LdapIdentifier` |
| `$_buildLoginUrlErrorResult()` | `buildLoginUrlErrorResult()` | `EnvironmentAuthenticator`, `FormAuthenticator` |
| `$_checkLdapConfig()` | `checkLdapConfig()` | `LdapIdentifier` |
| `$_checkPassword()` | `checkPassword()` | `PasswordIdentifier` |
| `$_checkSingleUrl()` | `checkSingleUrl()` | `MultiUrlChecker` |
| `$_checkUrl()` | `checkUrl()` | `UrlCheckerTrait` |
| `$_connectLdap()` | `connectLdap()` | `LdapIdentifier` |
| `$_create()` | `create()` | `AuthenticatorCollection` |
| `$_createCookie()` | `createCookie()` | `CookieAuthenticator` |
| `$_createLegacyPlainToken()` | `createLegacyPlainToken()` | `CookieAuthenticator` |
| `$_createToken()` | `createToken()` | `CookieAuthenticator` |
| `$_expiryTimestamp()` | `expiryTimestamp()` | `CookieAuthenticator` |
| `$_findIdentity()` | `findIdentity()` | `PasswordIdentifier` |
| `$_getChecker()` | `getChecker()` | `StringUrlChecker` |
| `$_getData()` | `getData()` | `FormAuthenticator` |
| `$_getUrlFromRequest()` | `getUrlFromRequest()` | `DefaultUrlChecker`, `StringUrlChecker` |
| `$_handleLdapError()` | `handleLdapError()` | `LdapIdentifier` |
| `$_hmacKey()` | `hmacKey()` | `CookieAuthenticator` |
| `$_isSingleRoute()` | `isSingleRoute()` | `MultiUrlChecker` |
| `$_legacyHashWithinLimits()` | `legacyHashWithinLimits()` | `CookieAuthenticator` |
| `$_mergeDefaultOptions()` | `mergeDefaultOptions()` | `DefaultUrlChecker`, `MultiUrlChecker`, `StringUrlChecker` |
| `$_resolveClassName()` | `resolveClassName()` | `AuthenticatorCollection` |
| `$_setErrorHandler()` | `setErrorHandler()` | `ExtensionAdapter` |
| `$_throwMissingClassError()` | `throwMissingClassError()` | `AuthenticatorCollection` |
| `$_unsetErrorHandler()` | `unsetErrorHandler()` | `ExtensionAdapter` |

The [cakephp/upgrade](https://github.com/cakephp/upgrade) tool knows about the
CakePHP 6 renames and can apply most of them for you.

### Fluent Methods Now Return `static`

These methods now declare a `static` return type. If you override any of them,
add the same return type to your override:

- `AuthenticationComponent::allowUnauthenticated()`
- `AuthenticationComponent::addUnauthenticatedActions()`
- `AuthenticationComponent::setIdentity()`
- `AuthenticationComponent::replaceIdentity()`
- `AuthenticationComponent::impersonate()`
- `AuthenticationComponent::stopImpersonating()`
- `AbstractAuthenticator::setIdentifier()`
- `PasswordHasherTrait::setPasswordHasher()`
- `ResolverAwareTrait::setResolver()`

**Before (4.x):**

```php
class CustomComponent extends AuthenticationComponent
{
    public function allowUnauthenticated(array $actions)
    {
        return parent::allowUnauthenticated($actions);
    }
}
```

**After (5.x):**

```php
class CustomComponent extends AuthenticationComponent
{
    public function allowUnauthenticated(array $actions): static
    {
        return parent::allowUnauthenticated($actions);
    }
}
```

### Constructor Changes

- The third argument of `Result::__construct()` was renamed from `$messages`
  to `$errors`. Named arguments such as `messages:` must be updated.
- The second argument of `AuthenticationMiddleware::__construct()` is typed as
  `Cake\Container\ContainerInterface` instead of `Cake\Core\ContainerInterface`.
- `AbstractAuthenticator`, `AuthenticationMiddleware`, `AuthenticationRequiredException`,
  `Identity` and `Result` use constructor promoted properties now. Subclasses
  that override these constructors should call `parent::__construct()` with the
  same arguments as before.

## Migration Tips

1. **Upgrade CakePHP first**: Finish the
   [CakePHP 6.0 Upgrade Guide](https://book.cakephp.org/6/en/appendices/6-0-upgrade-guide.html)
   and resolve all deprecation warnings while you are still on CakePHP 5.

2. **Search and replace** the renamed members in your custom authenticators,
   identifiers, password hashers, URL checkers and components:

    - `$_defaultConfig` → `$defaultConfig`
    - `$_defaultOptions` → `$defaultOptions`
    - `$_identifier` → `$identifier`
    - `$_config` → `$config`
    - `$this->_checkUrl(` → `$this->checkUrl(`
    - Any other `$_method()` or `$_property` of the plugin's classes

3. **Add return types** to overridden fluent methods so they match the new
   `static` return types.

4. **Run your test suite** with the new dependencies to find remaining
   references to the old names.
