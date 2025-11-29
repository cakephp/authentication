Redirect Validation
###################

The Authentication plugin provides optional redirect validation to prevent redirect loop attacks
and malicious redirect patterns that could be exploited by bots or attackers.

.. _security-redirect-loops:

Preventing Redirect Loops
==========================

By default, the authentication service does not validate redirect URLs beyond checking that they
are relative (not external). This means that malicious actors or misconfigured bots could create
deeply nested redirect chains like:

.. code-block:: text

    /login?redirect=/login?redirect=/login?redirect=/protected/page

These nested redirects can waste server resources, pollute logs, and potentially enable security
exploits.

Enabling Redirect Validation
=============================

To enable redirect validation, configure the ``redirectValidation`` option in your
``AuthenticationService``:

.. code-block:: php

    // In src/Application.php getAuthenticationService() method
    $service = new AuthenticationService();
    $service->setConfig([
        'unauthenticatedRedirect' => '/users/login',
        'queryParam' => 'redirect',
        'redirectValidation' => [
            'enabled' => true,  // Enable validation (default: false)
        ],
    ]);

Configuration Options
=====================

The ``redirectValidation`` configuration accepts the following options:

enabled
    **Type:** ``bool`` | **Default:** ``false``

    Whether to enable redirect validation. Disabled by default for backward compatibility.

maxDepth
    **Type:** ``int`` | **Default:** ``1``

    Maximum number of nested redirect parameters allowed. For example, with ``maxDepth`` set to 1,
    ``/login?redirect=/articles`` is valid, but ``/login?redirect=/login?redirect=/articles`` is blocked.

maxEncodingLevels
    **Type:** ``int`` | **Default:** ``1``

    Maximum URL encoding levels allowed. This prevents obfuscation attacks using double or triple
    encoding (e.g., ``%252F`` for double-encoded ``/``).

maxLength
    **Type:** ``int`` | **Default:** ``2000``

    Maximum allowed length of the redirect URL in characters. This helps prevent DOS attacks
    via excessively long URLs.

Example Configuration
=====================

Here's a complete example with custom configuration:

.. code-block:: php

    $service = new AuthenticationService();
    $service->setConfig([
        'unauthenticatedRedirect' => '/users/login',
        'queryParam' => 'redirect',
        'redirectValidation' => [
            'enabled' => true,
            'maxDepth' => 1,
            'maxEncodingLevels' => 1,
            'maxLength' => 2000,
        ],
    ]);

How Validation Works
====================

When redirect validation is enabled and a redirect URL fails validation, ``getLoginRedirect()``
will return ``null`` instead of the invalid URL. Your application should handle this by
redirecting to a default location:

.. code-block:: php

    // In your controller
    $target = $this->Authentication->getLoginRedirect() ?? '/';
    return $this->redirect($target);

Validation Checks
=================

The validation performs the following checks in order:

1. **Redirect Depth**: Counts occurrences of ``redirect=`` in the decoded URL
2. **Encoding Level**: Counts occurrences of ``%25`` (percent-encoded percent sign)
3. **URL Length**: Checks total character count

If any check fails, the URL is rejected.

Custom Validation
=================

You can extend ``AuthenticationService`` and override the ``validateRedirect()`` method
to implement custom validation logic, such as blocking specific URL patterns:

.. code-block:: php

    namespace App\Auth;

    use Authentication\AuthenticationService;

    class CustomAuthenticationService extends AuthenticationService
    {
        protected function validateRedirect(string $redirect): ?string
        {
            // Call parent validation first
            $redirect = parent::validateRedirect($redirect);

            if ($redirect === null) {
                return null;
            }

            // Add your custom validation
            // Example: Block redirects to authentication pages
            if (preg_match('#/(login|logout|register)#i', $redirect)) {
                return null;
            }

            // Example: Block redirects to admin areas
            if (str_contains($redirect, '/admin')) {
                return null;
            }

            return $redirect;
        }
    }

Backward Compatibility
======================

Redirect validation is **disabled by default** to maintain backward compatibility with existing
applications. To enable it, explicitly set ``'enabled' => true`` in the configuration.

Security Considerations
=======================

While redirect validation helps prevent common attacks, it should be part of a comprehensive
security strategy that includes:

* Rate limiting to prevent bot abuse
* Monitoring and logging of blocked redirects
* Regular security audits
* Keeping the Authentication plugin up to date

Real-World Attack Example
=========================

In production environments, bots (especially AI crawlers like GPTBot) have been observed
creating redirect chains with 6-7 levels of nesting:

.. code-block:: text

    /login?redirect=%2Flogin%3Fredirect%3D%252Flogin%253Fredirect%253D...

Enabling redirect validation prevents these attacks and protects your application from:

* Resource exhaustion (CPU wasted parsing deeply nested URLs)
* Log pollution (malformed URLs flooding access logs)
* SEO damage (search engines indexing login pages with loops)
* Potential security exploits when combined with other vulnerabilities

For more information on redirect attacks, see:

* `OWASP: Unvalidated Redirects and Forwards <https://owasp.org/www-community/attacks/Unvalidated_Redirects_and_Forwards>`_
* `CWE-601: URL Redirection to Untrusted Site <https://cwe.mitre.org/data/definitions/601.html>`_
