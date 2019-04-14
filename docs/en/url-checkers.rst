URL Checkers
############

To provide an abstract and framework agnostic solution there are URL
checkers implemented that allow you to customize the comparision of the
current URL if needed. For example to another frameworks routing.

Included Checkers
=================

DefaultUrlChecker
-----------------

The default checker allows you to compare an URL by regex or string
URLs.

Options:

-  **checkFullUrl**: To compare the full URL, including protocol, host
   and port or not. Default is ``false``
-  **useRegex**: Compares the URL by a regular expression provided in
   the ``$loginUrls`` argument of the checker.

CakeRouterUrlChecker
--------------------

Options:

Use this checker if you want to use the array notation of CakePHPs
routing system. The checker also works with named routes.

-  **checkFullUrl**: To compare the full URL, including protocol, host
   and port or not. Default is ``false``

Implementing your own Checker
-----------------------------

An URL checker **must** implement the ``UrlCheckterInterface``.
