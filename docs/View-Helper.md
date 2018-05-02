# View Helper

In your AppView, load the Helper as
```php
$this->loadHelper('Authentication.Identity');
```

For very simple checking whether the user is logged in you can use
```php
if ($this->Identify->isLoggedIn()) {
    ...
}
```

Getting user data is as as easy as
```php
$username = $this->Identity->get('username');
```

The following check can be used to tell if a record that belongs to some user is
the current logged in user and compare other fields as well:
```php
$isCurrentUser = $this->Identity->is($user->id);
$isCurrentRole = $this->Identity->is($user->role_id, 'role_id');
```

This method is mostly a convenience method for simple cases and not intended 
to replace any kind of proper authorization implementation.
