# Authentication Component

You can use the `AuthenticationComponent` to access the result of
authentication, get user identity and logout user. Load the component in your
`AppController::initialize()` like any other component:

```php
$this->loadComponent('Authentication.Authentication', [
    'logoutRedirect' => '/users/login'  // Default is false
]);
```

Once loaded, the `AuthenticationComponent` will require that all actions have an
authenticated user present, but perform no other access control checks. You can
disable this check for specific actions using `allowUnauthenticated()`:

```php
// In your controller's beforeFilter method.
$this->Authentication->allowUnauthenticated(['view']);
```

## Accessing the logged in user

You can get the authenticated user identity data using the authentication
component:

```php
$user = $this->Authentication->getIdentity();
```

You can also get the identity directly from the request instance:

```php
$user = $request->getAttribute('identity');
```

## Checking the login status

You can check if the authentication process was successful by accessing the
result object:

```php
// Using Authentication component
$result = $this->Authentication->getResult();

// Using request object
$result = $request->getAttribute('authentication')->getResult();

if ($result->isValid()) {
    $user = $request->getAttribute('identity');
} else {
    $this->log($result->getStatus());
    $this->log($result->getErrors());
}
```

The result sets objects status returned from `getStatus()` will match one of
these constants in the Result object:

- `ResultInterface::SUCCESS`, when successful.
- `ResultInterface::FAILURE_IDENTITY_NOT_FOUND`, when identity could not be found.
- `ResultInterface::FAILURE_CREDENTIALS_INVALID`, when credentials are invalid.
- `ResultInterface::FAILURE_CREDENTIALS_MISSING`, when credentials are missing in the request.
- `ResultInterface::FAILURE_OTHER`, on any other kind of failure.

The error array returned by `getErrors()` contains **additional** information
coming from the specific system against which the authentication attempt was
made. For example LDAP or OAuth would put errors specific to their
implementation in here for easier logging and debugging the cause. But most of
the included authenticators don't put anything in here.

## Logging out the identity

To log an identity out just do:

```php
$this->Authentication->logout();
```

If you have set the `logoutRedirect` config, `Authentication::logout()` will
return that value else will return `false`. It won't perform any actual redirection
in either case.

Alternatively, instead of the component you can also use the service to log out:

```php
$return = $request->getAttribute('authentication')->clearIdentity($request, $response);
```

The result returned will contain an array like this:

```php
[
    'response' => object(Cake\Http\Response) { ... },
    'request' => object(Cake\Http\ServerRequest) { ... },
]
```

> [!NOTE]
> This will return an array containing the request and response
> objects. Since both are immutable you'll get new objects back. Depending on your
> context you're working in you'll have to use these instances from now on if you
> want to continue to work with the modified response and request objects.

## Replacing the current identity

Use `setIdentity()` to change which user is logged in (e.g. after registration
or social-login first-touch). It clears all persisted identity data and writes
the new identity through every persisting authenticator:

```php
$this->Authentication->setIdentity($user);
```

> [!WARNING]
> `setIdentity()` ends an active impersonation session by default, because it
> goes through `clearIdentity()` first, which calls `stopImpersonating()` on
> impersonation-aware authenticators. Use `replaceIdentity()` below for the
> request-only refresh case.

### Refresh the active identity for the current request only

When you only need to swap the in-request identity (for example to attach
eager-loaded associations or computed flags in `beforeFilter()`) without
touching the session or persistence, use `replaceIdentity()`:

```php
// AppController::beforeFilter()
$identity = $this->Authentication->getIdentity();
if ($identity && !$identity->some_association) {
    $reloaded = $this->fetchTable('Users')
        ->get($identity->getIdentifier(), finder: 'fullProfile');
    $this->Authentication->replaceIdentity($reloaded);
}
```

This rewrites only the request attribute. The session is not modified, so an
active impersonation is preserved and no privilege-escalation side effects
(like session rotation) occur.

See [User Impersonation](impersonation.md) for the broader context.

## Configure Automatic Identity Checks

By default `AuthenticationComponent` will automatically enforce an identity to
be present during the `Controller.startup` event. You can have this check
applied during the `Controller.initialize` event instead:

```php
// In your controller's initialize() method.
$this->loadComponent('Authentication.Authentication', [
    'identityCheckEvent' => 'Controller.initialize',
]);
```

You can also disable identity checks entirely with the `requireIdentity`
option or by calling `disableIdentityCheck` from the controller's `beforeFilter()` method itself:

```php
$this->Authentication->disableIdentityCheck();
```

## Redirecting after login

For the common post-login redirect flow, use `redirectAfterLogin()`:

```php
public function login(): ?\Cake\Http\Response
{
    $result = $this->Authentication->getResult();

    if ($result && $result->isValid()) {
        return $this->Authentication->redirectAfterLogin('/home');
    }

    return null;
}
```

This uses the plugin's validated login redirect target from the current
request when available and falls back to the default you provide.

If you need to inspect the validated target before redirecting, use
`getLoginRedirect()` instead:

```php
$target = $this->Authentication->getLoginRedirect('/home');
return $this->redirect($target);
```

Avoid reading raw `redirect` query string parameters and passing them directly
to the controller's `redirect()` method.
