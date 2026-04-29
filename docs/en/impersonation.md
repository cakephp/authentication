# User Impersonation

After deploying your application, you may occasionally need to
'impersonate' another user in order to debug problems that your customers report
or to see the application in the state that your customers are seeing it.

## Enabling Impersonation

To impersonate another user you can use the `impersonate()` method on the
`AuthenticationComponent`. To impersonate a user you first need to load that
user from your application's database:

```php
// In a controller
public function impersonate(): \Cake\Http\Response
{
    $this->request->allowMethod(['POST']);

    $currentUser = $this->request->getAttribute('identity');

    // You should always check that the current user is allowed
    // to impersonate other users first.
    if (!$currentUser->isStaff()) {
        throw new NotFoundException();
    }

    // Fetch the user we want to impersonate.
    $targetUser = $this->fetchTable('Users')
        ->findById($this->request->getData('user_id'))
        ->firstOrFail();

    // Enable impersonation.
    $this->Authentication->impersonate($targetUser);

    return $this->redirect($this->referer());
}
```

Once you have started to impersonate a user, all subsequent requests will have
`$targetUser` as the active identity.

## Ending Impersonation

Once you are done impersonating a user, you can then end impersonation and revert
back to your previous identity using `AuthenticationComponent`:

```php
// In a controller
public function revertIdentity(): \Cake\Http\Response
{
    $this->request->allowMethod(['POST']);

    // Make sure we are still impersonating a user.
    if (!$this->Authentication->isImpersonating()) {
        throw new NotFoundException();
    }
    $this->Authentication->stopImpersonating();

    return $this->redirect($this->referer());
}
```

## Limitations to Impersonation

There are a few limitations to impersonation.

1. Your application must be using the `Session` authenticator.
2. You cannot impersonate another user while impersonation is active. Instead
    you must `stopImpersonating()` and then start it again.
3. Calling `setIdentity()` or `clearIdentity()` (and therefore `logout()`)
    ends impersonation by default. The service's `clearIdentity()` actively
    calls `stopImpersonating()` on impersonation-aware authenticators, so any
    code path that swaps the persisted identity will revert to the original
    user.

    To refresh the active identity without disturbing impersonation, use one
    of the dedicated methods on `AuthenticationComponent`:

    - `replaceIdentity($identity)` updates the in-request identity attribute
      only. The session is not touched. Use this for the common
      `beforeFilter()` case of attaching eager-loaded associations to the
      active user for the rest of the request.
    - `setIdentity($identity, preserveImpersonation: true)` persists the new
      identity into the session like the default flow, but keeps the
      impersonation slot intact. Use this when the refresh has to survive
      into subsequent requests.

    See [Replacing the current identity](authentication-component.md#replacing-the-current-identity)
    for examples.
