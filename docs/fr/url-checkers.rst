Vérificateurs d'URL
###################

Afin de fournir une solution abstraite et ignorante du framework, des
vérificateurs d'URL ont été implémentés qui vous permettent de personnaliser si
besoin la comparaison de l'URL en cours, par exemple avec le routage d'un autre
framework.

Vérificateurs inclus
====================

DefaultUrlChecker
-----------------

Le vérificateur par défaut vous permet de comparer une URL par expression
régulière ou chaînes URL.

Options:

-  **checkFullUrl**: Pour comparer l'URL entière, y compris le protocole, l'hôte
   et le port, ou pas. La valeur par défaut est ``false``
-  **useRegex**: Compare l'URL par une expression régulière fournie dans
   l'argument ``$loginUrls`` du vérificateur.

CakeRouterUrlChecker
--------------------

Options:

Utilisez ce vérificateur si vous voulez utiliser la notation en tableaux du
système de routage de CakePHP. Le vérificateur marche aussi avec les routes
nommées (*named routes*).

-  **checkFullUrl**: Pour comparer l'URL entière, y compris le protocole, l'hôte
   et le port, ou pas. La valeur par défaut est ``false``

Implémenter votre propre Vérificateur
-------------------------------------

Un vérificateur d'URL **doit** implémenter l'interface ``UrlCheckerInterface``.
