# Identifiers

Identifiers will identify an user or service based on the information that was extracted from the request by the authenticators.

## ORM

The ORM identifier checks the passed credentials against a datasource.

Configuration options:

* **fields**: The fiels for the lookup. Default is `['username' => 'username', 'password' => 'password']`.
* **userModel**: The user model. Default is `Users` and all pages will be checked.
* **finder**: The finder to use with the model. Default is `all`.
* **passwordHasher**: Password hasher. Default is `DefaultPasswordHasher::class`.

## Token

Checks the passed token against a datasource.

Configuration options:

* **tokenField**: The field in the database to check against. Default is `token`.
* **dataField**: The field in the passed data from the authenticator. Default is `token`.
* **userModel**: The user model. Default is `Users` and all pages will be checked.
* **finder**: Finder method in the model. Default is `all`.
* **tokenVerification**: The verification method. Default is `Orm`.

## JWT Subject

Checks the passed JWT token against a datasource.

* **tokenField**: Default is `id`.
* **dataField**: Default is `sub`.
* **userModel**: The user model. Default is `Users` and all pages will be checked.
* **finder**: Finder method in the model. Default is `all`.
* **tokenVerification**: The verification method. Default is `Orm`.

## Callback

Allows you to use a callback for identification. This is useful for simple identifiers or quick prototyping.

Configuration options:

* **callback**: Default is `null` and will cause an exception. You're required to pass a valid callback to this option to use the authenticator.
