# Upgrade Guide 3.x to 4.x

Version 4.0 is a major release with several breaking changes focused on
simplifying the API and removing deprecated code.

## Breaking Changes

### IdentifierCollection Removed

The deprecated `IdentifierCollection` has been removed. Authenticators now
accept a nullable `IdentifierInterface` directly.

**Before (3.x):**

```php
use Authentication\Identifier\IdentifierCollection;

$identifiers = new IdentifierCollection([
    'Authentication.Password',
]);

$authenticator = new FormAuthenticator($identifiers);
```

**After (4.x):**

```php
use Authentication\Identifier\IdentifierFactory;

// Option 1: Pass identifier directly
$identifier = IdentifierFactory::create('Authentication.Password');
$authenticator = new FormAuthenticator($identifier);

// Option 2: Pass null and let authenticator create default
$authenticator = new FormAuthenticator(null);

// Option 3: Configure identifier in authenticator config
$service->loadAuthenticator('Authentication.Form', [
    'identifier' => 'Authentication.Password',
]);
```

#### AuthenticationService Changes

The `loadIdentifier()` method has been removed from `AuthenticationService`.
Identifiers are now managed by individual authenticators.

**Before (3.x):**

```php
$service = new AuthenticationService();
$service->loadIdentifier('Authentication.Password');
$service->loadAuthenticator('Authentication.Form');
```

**After (4.x):**

```php
$service = new AuthenticationService();
$service->loadAuthenticator('Authentication.Form', [
    'identifier' => 'Authentication.Password',
]);
```

### CREDENTIAL Constants Moved

The `CREDENTIAL_USERNAME` and `CREDENTIAL_PASSWORD` constants have been
moved from `AbstractIdentifier` to specific identifier implementations.

**Before (3.x):**

```php
use Authentication\Identifier\AbstractIdentifier;

$fields = [
    AbstractIdentifier::CREDENTIAL_USERNAME => 'email',
    AbstractIdentifier::CREDENTIAL_PASSWORD => 'password',
];
```

**After (4.x):**

```php
use Authentication\Identifier\PasswordIdentifier;

$fields = [
    PasswordIdentifier::CREDENTIAL_USERNAME => 'email',
    PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
];
```

For LDAP authentication:

```php
use Authentication\Identifier\LdapIdentifier;

$fields = [
    LdapIdentifier::CREDENTIAL_USERNAME => 'uid',
    LdapIdentifier::CREDENTIAL_PASSWORD => 'password',
];
```

### SessionAuthenticator `identify` Option Removed

The deprecated `identify` option has been removed from `SessionAuthenticator`.
Use `PrimaryKeySessionAuthenticator` instead if you need to fetch fresh user
data from the database on each request.

**Before (3.x):**

```php
$service->loadAuthenticator('Authentication.Session', [
    'identify' => true,
    'identifier' => 'Authentication.Password',
]);
```

**After (4.x):**

```php
$service->loadAuthenticator('Authentication.PrimaryKeySession');
```

### URL Checker Renamed and Restructured

URL checkers have been completely restructured:

- `CakeRouterUrlChecker` has been renamed to `DefaultUrlChecker`
- The old `DefaultUrlChecker` has been renamed to `StringUrlChecker`
- Auto-detection has been removed - `DefaultUrlChecker` is now hardcoded

**Before (3.x):**

```php
// Using CakeRouterUrlChecker explicitly
$service->loadAuthenticator('Authentication.Form', [
    'urlChecker' => 'Authentication.CakeRouter',
    'loginUrl' => [
        'controller' => 'Users',
        'action' => 'login',
    ],
]);

// Using DefaultUrlChecker explicitly
$service->loadAuthenticator('Authentication.Form', [
    'urlChecker' => 'Authentication.Default',
    'loginUrl' => '/users/login',
]);

// Auto-detection (picks CakeRouter if available, otherwise Default)
$service->loadAuthenticator('Authentication.Form', [
    'loginUrl' => '/users/login',
]);
```

**After (4.x):**

```php
// DefaultUrlChecker is now hardcoded (formerly CakeRouterUrlChecker)
$service->loadAuthenticator('Authentication.Form', [
    'loginUrl' => [
        'controller' => 'Users',
        'action' => 'login',
    ],
]);

// For string-only URL checking, explicitly use StringUrlChecker
$service->loadAuthenticator('Authentication.Form', [
    'urlChecker' => 'Authentication.String',
    'loginUrl' => '/users/login',
]);
```

### Simplified URL Checker API

URL checkers now accept a single URL in either string or array format.
For multiple URLs, you must explicitly use `MultiUrlChecker`.

**Multiple URLs - Before (3.x):**

```php
// This would auto-select the appropriate checker
$service->loadAuthenticator('Authentication.Form', [
    'loginUrl' => [
        '/en/users/login',
        '/de/users/login',
    ],
]);
```

**Multiple URLs - After (4.x):**

```php
// Must explicitly configure MultiUrlChecker
$service->loadAuthenticator('Authentication.Form', [
    'urlChecker' => 'Authentication.Multi',
    'loginUrl' => [
        '/en/users/login',
        '/de/users/login',
    ],
]);
```

Single URLs work the same in both versions:

```php
// String URL
$service->loadAuthenticator('Authentication.Form', [
    'loginUrl' => '/users/login',
]);

// Array URL (CakePHP route)
$service->loadAuthenticator('Authentication.Form', [
    'loginUrl' => ['controller' => 'Users', 'action' => 'login'],
]);
```

### Auto-Detection Removed

#### URL Checkers

**Important:** Auto-detection has been removed. `DefaultUrlChecker` is now hardcoded.

- **4.x default:** Always uses `DefaultUrlChecker` (formerly `CakeUrlChecker`)
- **String URLs only:** Must explicitly configure `StringUrlChecker`
- **Multiple URLs:** Must explicitly configure `MultiUrlChecker`

#### DefaultUrlChecker is Now CakePHP-Based

`DefaultUrlChecker` is now the CakePHP checker (formerly `CakeRouterUrlChecker`).
It requires CakePHP Router and supports both string and array URLs.

The 3.x `DefaultUrlChecker` has been renamed to `StringUrlChecker`.

```php
// DefaultUrlChecker now requires CakePHP Router
$checker = new DefaultUrlChecker();
$checker->check($request, ['controller' => 'Users', 'action' => 'login']);  // Works
$checker->check($request, '/users/login');  // Also works

// For string URL only usage:
$checker = new StringUrlChecker();
$checker->check($request, '/users/login');  // Works
$checker->check($request, ['controller' => 'Users']);  // Throws exception
```

## New Features

### IdentifierFactory

New factory class for creating identifiers from configuration:

```php
use Authentication\Identifier\IdentifierFactory;

// Create from string
$identifier = IdentifierFactory::create('Authentication.Password');

// Create with config
$identifier = IdentifierFactory::create('Authentication.Password', [
    'fields' => [
        'username' => 'email',
        'password' => 'password',
    ],
]);

// Pass existing instance (returns as-is)
$identifier = IdentifierFactory::create($existingIdentifier);
```

### MultiUrlChecker

New dedicated checker for multiple login URLs:

```php
$service->loadAuthenticator('Authentication.Form', [
    'urlChecker' => 'Authentication.Multi',
    'loginUrl' => [
        '/en/login',
        '/de/login',
        ['lang' => 'fr', 'controller' => 'Users', 'action' => 'login'],
    ],
]);
```

## Migration Tips

1. **Session Identify**:

    If you used `'identify' => true` on `SessionAuthenticator`, switch to
    `PrimaryKeySessionAuthenticator` which always fetches fresh data.

2. **Search and Replace**:

    - `AbstractIdentifier::CREDENTIAL_` → `PasswordIdentifier::CREDENTIAL_`
    - `IdentifierCollection` → `IdentifierFactory`
    - `'Authentication.CakeRouter'` → Remove (no longer needed, default is now CakePHP-based)
    - `CakeRouterUrlChecker` → `DefaultUrlChecker`
    - Old 3.x `DefaultUrlChecker` → `StringUrlChecker`

3. **String URL Checking**:

    If you want to use string-only URL checking, explicitly configure
    `StringUrlChecker`:

    ```php
    $service->loadAuthenticator('Authentication.Form', [
        'urlChecker' => 'Authentication.String',
        'loginUrl' => '/users/login',
    ]);
    ```

4. **Multiple Login URLs**:

    If you have multiple login URLs, add `'urlChecker' => 'Authentication.Multi'`
    to your authenticator configuration.

5. **Custom Identifier Setup**:

    If you were passing `IdentifierCollection` to authenticators, switch to
    either passing a single identifier or null (to use defaults).

6. **Test Thoroughly**:

    The changes to identifier management and URL checking are significant.
    Test all authentication flows after upgrading.
