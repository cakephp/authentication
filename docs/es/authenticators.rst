Autenticadores
##############

Los autenticadores se encargan de convertir los datos de la request en operaciones de
autenticación. Utilizan :doc:`/identifiers` para encontrar un
:doc:`/identity-object` conocido.

Sesión
======

Este autenticador comprobará si la sesión contiene datos de usuario o
credenciales. Cuando utilice un autenticador con estado como el ``Form`` listado
más abajo, asegúrese de cargar primero el autenticador de ``Session`` para que una vez
el usuario inicie sesión, los datos del usuario se obtengan de la sesión en las requests
posteriores.

Opciones de configuración:

-  **sessionKey**: Key para los datos de usuario, por defecto es
   ``Auth``
-  **identify**: Establezca esta key con un valor ``true`` para permitir la verificación de las
   credenciales de sesión con los identificadores. Cuando es ``true``, los
   :doc:`/identifiers` configurados se utilizan para identificar al usuario utilizando los datos
   almacenados en la sesión en cada request. El valor predeterminado es ``false``.
-  **fields**: Permite mapear el campo ``username`` al identificador único
   en su almacenamiento de usuario. Por defecto es ``username``. Esta opción se utiliza cuando
   la opción ``identify`` se establece en verdadero.

Form
====

Busca los datos en el cuerpo de la request, generalmente cuando se envía un
formulario vía POST / PUT.

Opciones de configuración:

-  **loginUrl**: La URL login, puede ser un string o un array de URLs. Por defecto es
   ``null`` y se comprobarán todas las páginas.
-  **fields**: Array que mapea ``username`` y ``password`` a los campos de
   datos POST especificados.
-  **urlChecker**: La clase u objeto comprobador de URL. Por defecto es
   ``DefaultUrlChecker``.
-  **useRegex**: Usar o no expresiones regulares para la coincidencia de URL
   Por defecto es ``false``.
-  **checkFullUrl**: Comprobar o no la URL completa incluida en la query
   string. Útil cuando un formulario login está en un subdominio diferente. Por defecto es
   ``false``. Esta opción no funciona bien cuando se conservan los redireccionamientos
   no autenticados en la query string.

Si está creando una API y desea aceptar credenciales via JSON requests asegúrese
de tener el ``BodyParserMiddleware`` aplicado **antes** del
``AuthenticationMiddleware``.

.. warning::
    Si usa la sintaxis de array para la URL, la URL será generada
    por el router de CakePHP. El resultado **podría** diferir de lo que realmente tiene
    en la request URI según el manejo de su ruta. ¡Debe considerar que es sensible
    a mayúsculas y minúsculas!

Token
=====

El token autenticador puede autenticar una request basada en un token que viene
junto con la request en los headers o en los parámetros de ésta.

Opciones de configuración:

-  **queryParam**: Nombre del parámetro de la query. Configúrelo si desea obtener
   el token de los parámetros de la query.
-  **header**: Nombre del header. Configúrelo si desea obtener el token
   del encabezado.
-  **tokenPrefix**: Prefijo opcional del token.

Un ejemplo de cómo obtener un token de un header o una query string sería::

    $service->loadAuthenticator('Authentication.Token', [
        'queryParam' => 'token',
        'header' => 'Authorization',
        'tokenPrefix' => 'Token'
    ]);

Lo anterior leería el parámetro GET del ``token`` o el header ``Authorization``
siempre que el token estuviera precedido por ``Token`` y un espacio.

El token siempre se pasará al identificador configurado de la siguiente manera::


    [
        'token' => '{token-value}',
    ]

JWT
===

El autenticador JWT obtiene el `JWT token <https://jwt.io/>`__ del header o el parámetro
de la query y devuelve el payload directamente o lo pasa
a los identificadores para verificarlos con otra fuente de datos por
ejemplo.

-  **header**: Línea del header para verificar el token. Por defecto es
   ``Authorization``.
-  **queryParam**: Parámetro de la query para verificar el token. Por defecto
   es ``token``.
-  **tokenPrefix**: Prefijo del token. Por defecto es ``bearer``.
-  **algorithm**: El algoritmo de hash para Firebase JWT. Por defecto es ``'HS256'``.
-  **returnPayload**: Retornar o no el payload del token directamente
   sin pasar a través de los identificadores. Por defecto es ``true``.
-  **secretKey**: Por defecto es ``null`` pero será **requerido** pasar una
   key secreta si no está en el contexto de una aplicación CakePHP que la
   provee mediante ``Security::salt()``.

Por defecto, el ``JwtAuthenticator`` usa el algoritmo de key simétrica ``HS256``
y usa el valor de ``Cake\Utility\Security::salt()`` como key de cifrado.
Para mayor seguridad, se puede utilizar en su lugar el algoritmo de key asimétrica ``RS256``.
Puede generar las keys necesarias para eso de la siguiente manera::

    # generate private key
    openssl genrsa -out config/jwt.key 1024
    # generate public key
    openssl rsa -in config/jwt.key -outform PEM -pubout -out config/jwt.pem

El archivo ``jwt.key`` es la key privada y debe mantenerse a salvo.
El archivo ``jwt.pem`` es la key pública. Este archivo debe usarse cuando necesite verificar tokens
creados por aplicaciones externas, por ejemplo: aplicaciones móviles.

El siguiente ejemplo le permite identificar al usuario basado en el ``sub`` (asunto) del token
usando el identificador ``JwtSubject`` y configura el ``Authenticator`` para usar la key pública
para la verificación del token.

Agregue lo siguiente a su clase ``Application``::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        // ...
        $service->loadIdentifier('Authentication.JwtSubject');
        $service->loadAuthenticator('Authentication.Jwt', [
            'secretKey' => file_get_contents(CONFIG . '/jwt.pem'),
            'algorithm' => 'RS256',
            'returnPayload' => false
        ]);
    }

En su ``UsersController``::

    use Firebase\JWT\JWT;

    public function login()
    {
        $result = $this->Authentication->getResult();
        if ($result->isValid()) {
            $privateKey = file_get_contents(CONFIG . '/jwt.key');
            $user = $result->getData();
            $payload = [
                'iss' => 'myapp',
                'sub' => $user->id,
                'exp' => time() + 60,
            ];
            $json = [
                'token' => JWT::encode($payload, $privateKey, 'RS256'),
            ];
        } else {
            $this->response = $this->response->withStatus(401);
            $json = [];
        }
        $this->set(compact('json'));
        $this->viewBuilder()->setOption('serialize', 'json');
    }

Además de compartir el archivo de key pública con una aplicación externa, puede
distribuirlo a través de un endpoint JWKS configurando su aplicación de la siguiente manera::

    // config/routes.php
    $builder->setExtensions('json');
    $builder->connect('/.well-known/:controller/*', [
        'action' => 'index',
    ], [
        'controller' => '(jwks)',
    ]); // connect /.well-known/jwks.json to JwksController

    // controller/JwksController.php
    public function index()
    {
        $pubKey = file_get_contents(CONFIG . './jwt.pem');
        $res = openssl_pkey_get_public($pubKey);
        $detail = openssl_pkey_get_details($res);
        $key = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'e' => JWT::urlsafeB64Encode($detail['rsa']['e']),
            'n' => JWT::urlsafeB64Encode($detail['rsa']['n']),
        ];
        $keys['keys'][] = $key;

        $this->viewBuilder()->setClassName('Json');
        $this->set(compact('keys'));
        $this->viewBuilder()->setOption('serialize', 'keys');
    }

Ir a https://datatracker.ietf.org/doc/html/rfc7517 o https://auth0.com/docs/tokens/json-web-tokens/json-web-key-sets para
mas información sobre JWKS.

HttpBasic
=========

Ver https://en.wikipedia.org/wiki/Basic_access_authentication

Opciones de configuración:

-  **realm**: Por defecto es ``$_SERVER['SERVER_NAME']`` sobreescribirlo como
   sea necesario.

HttpDigest
==========

Ver https://en.wikipedia.org/wiki/Digest_access_authentication

Opciones de configuración:

-  **realm**: Por defecto es ``null``
-  **qop**: Por defecto es ``auth``
-  **nonce**: Por defecto es ``uniqid(''),``
-  **opaque**: Por defecto es ``null``

Cookie Authenticator también conocido como "Remember Me"
========================================================

El Autenticador de cookies le permite implementar la función "remember me"
para sus formularios de login.

Solo asegúrese de que su formulario de login tenga un campo que coincida
con el nombre del campo que está configurado en este authenticator.

Para cifrar y descifrar su cookie, asegúrese de haber agregado
EncryptedCookieMiddleware a su aplicación *antes* del
AuthenticationMiddleware.

Opciones de configuración:

-  **rememberMeField**: Por defecto es ``remember_me``
-  **cookie**: Array de opciones cookie:

   -  **name**: Nombre de la cookie, por defecto es ``CookieAuth``
   -  **expires**: Expiración, por defecto es ``null``
   -  **path**: Ruta, por defecto es ``/``
   -  **domain**: Dominio, por defecto es un string vacío.
   -  **secure**: Bool, por defecto es ``false``
   -  **httponly**: Bool, por defecto es ``false``
   -  **value**: Valor, por defecto es un string vacío.
   -  **samesite**: String/null El valor para el mismo atributo de sitio.

   Los valores predeterminados para las diversas opciones además de ``cookie.name`` serán
   los establecidos para la clase ``Cake\Http\Cookie\Cookie``. Consulte `Cookie::setDefaults() <https://api.cakephp.org/4.0/class-Cake.Http.Cookie.Cookie.html#setDefaults>`_
   para conocer los valores predeterminados.

-  **fields**: Array que mapea ``username`` y ``password`` a los campos
   de identidad especificados.
-  **urlChecker**: La clse u objeto verificador de URL. Por defecto es
   ``DefaultUrlChecker``.
-  **loginUrl**: The URL de login, string o array de URLs. Por defecto es
   ``null`` y todas las páginas serán verificadas.
-  **passwordHasher**: Hasher del password a usar para el hash del token. Po defecto
   es ``DefaultPasswordHasher::class``.

Uso
---

El autenticador de cookies se puede agregar a un sistema de autenticación basado
en Form & Session. La autenticación de cookies volverá a iniciar sesión automáticamente a los
usuarios después de que expire su sesión durante el tiempo que la cookie sea válida. Si un usuario
se desconecta explícitamente vía ``AuthenticationComponent::logout()``, la cookie de autenticación
**también se destruye**. Una configuración de ejemplo sería::

    // In Application::getAuthService()

    // Reuse fields in multiple authenticators.
    $fields = [
        IdentifierInterface::CREDENTIAL_USERNAME => 'email',
        IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
    ];

    // Put form authentication first so that users can re-login via
    // the login form if necessary.
    $service->loadAuthenticator('Authentication.Form', [
        'loginUrl' => '/users/login',
        'fields' => [
            IdentifierInterface::CREDENTIAL_USERNAME => 'email',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
        ],
    ]);
    // Then use sessions if they are active.
    $service->loadAuthenticator('Authentication.Session');

    // If the user is on the login page, check for a cookie as well.
    $service->loadAuthenticator('Authentication.Cookie', [
        'fields' => $fields,
        'loginUrl' => '/users/login',
    ]);

También deberá agregar una casilla de verificación a su formulario login para que se creen cookies::

    // In your login view
    <?= $this->Form->control('remember_me', ['type' => 'checkbox']);

Después de iniciar sesión, si se marcó la casilla de verificación, debería ver una cookie ``CookieAuth``
en las herramientas de desarrollo de su navegador. La cookie almacena el campo username y un token hash
que se usa para volver a autenticarse más tarde.

Eventos
=======

Solo hay un evento que se activa mediante autenticación:
``Authentication.afterIdentify``.

Si no sabe qué son los eventos y cómo utilizarlos, consulte la
documentación <https://book.cakephp.org/3.0/en/core-libraries/events.html>`__.

El evento ``Authentication.afterIdentify`` es activado por el
``AuthenticationComponent`` despues que una identity fue identificada
satisfactoriamente.

El evento contiene los siguientes datos:

-  **provider**: Un objeto que implementa
   ``\Authentication\Authenticator\AuthenticatorInterface``
-  **identity**: Un objeto que implementa ``\ArrayAccess``
-  **service**: Un objeto que implementa
   ``\Authentication\AuthenticationServiceInterface``

El asunto del evento será la instancia de controlador actual a la que
está adjunto el AuthenticationComponent.

Pero el evento solo se activa si el autenticador que se utilizó para identificar
la identity *no* es persistente y *no* es sin estado. La razón de esto es
que el evento se activaría cada vez porque el autenticador de sesión o el token,
por ejemplo, lo activaría cada vez para cada request.

De los autenticadores incluidos, solo FormAuthenticator hará que se dispare
el evento. Después de eso, el autenticador de sesión proporcionará la identidad.

Comprobadores de URL
====================

Algunos autenticadores como ``Form`` o ``Cookie`` deben ejecutarse solo
en ciertas páginas como la página ``/login``. Esto se puede lograr utilizando
comprobadores de URL.

De forma predeterminada, se usa un ``DefaultUrlChecker``, que usa URLs string
para comparar con soporte para la verificación de expresiones regulares.

Opciones de configuración:

-  **useRegex**: Usar o no expresiones regulares para coincidencia
   URL. Por defecto es ``false``.
-  **checkFullUrl**: Comprobar o no la URL completa. Útil cuando un formulario
   login está en un subdominio diferente. Por defecto es ``false``.

Se puede implementar un verificador de URL personalizado, por ejemplo,
si se necesita soporte para un famework URL específico. En este caso, debe implementarse
la ``Authentication\UrlChecker\UrlCheckerInterface``.

Para mas detalles de Comprobadores de URL Checkers :doc:`ver esta página de
la documentación </url-checkers>`.

Obtener el Successful Authenticator o el Identifier
===================================================

Después de que un usuario ha sido autenticado, es posible que desee inspeccionar o
interactuar con el Authenticator que autenticó correctamente al usuario::

    // In a controller action
    $service = $this->request->getAttribute('authentication');

    // Will be null on authentication failure, or an authenticator.
    $authenticator = $service->getAuthenticationProvider();

También puede obtener el identifier que identificó al usuario::

    // In a controller action
    $service = $this->request->getAttribute('authentication');

    // Will be null on authentication failure, or an identifier.
    $identifier = $service->getIdentificationProvider();


Uso de Stateless (sin estado) Authenticators con Stateful (con estado) Authenticators
=====================================================================================

Cuando se usa ``Token`` o ``HttpBasic``, ``HttpDigest`` con otros autenticadores,
debe recordar que estos autenticadores detendrán la request cuando las credenciales
de autenticación falten o no sean válidas. Esto es necesario ya que estos autenticadores
deben enviar challenge headers específicos en el response::

    use Authentication\AuthenticationService;

    // Instantiate the service
    $service = new AuthenticationService();

    // Load identifiers
    $service->loadIdentifier('Authentication.Password', [
        'fields' => [
            'username' => 'email',
            'password' => 'password'
        ]
    ]);
    $service->loadIdentifier('Authentication.Token');

    // Load the authenticators leaving Basic as the last one.
    $service->loadAuthenticator('Authentication.Session');
    $service->loadAuthenticator('Authentication.Form');
    $service->loadAuthenticator('Authentication.HttpBasic');

Si desea combinar ``HttpBasic`` o ``HttpDigest`` con otros autenticadores,
tenga en cuenta que estos autenticadores abortarán la request y forzarán
un cuadro de diálogo del navegador.

Manejo de Errores por no Autenticación
======================================

El ``AuthenticationComponent`` generará una excepción cuando los usuarios no estén
autenticados. Puede convertir esta excepción en una redirección utilizando el
``unauthenticatedRedirect`` al configurar el ``AuthenticationService``.

También puede pasar el URI de destino de la request actual como un parámetro
utilizando la opción ``queryParam``::

   // In the getAuthenticationService() method of your src/Application.php

   $service = new AuthenticationService();

   // Configure unauthenticated redirect
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

Luego, en el método login del controlador, puede usar ``getLoginRedirect()`` para obtener
del parámetro string de la query el destino de redireccionamiento de manera segura::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // Regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            // Use the redirect parameter if present.
            $target = $this->Authentication->getLoginRedirect();
            if (!$target) {
                $target = ['controller' => 'Pages', 'action' => 'display', 'home'];
            }
            return $this->redirect($target);
        }
    }

Múltiples Flujos de Autenticación
=================================

En una aplicación que proporciona tanto una API como una interfaz web,
es posible que desee diferentes configuraciones de autenticación en función de
si la request es una API request o no. Por ejemplo, puede utilizar la autenticación JWT
para su API, pero sesiones para su interfaz web. Para admitir este flujo, puede
devolver diferentes servicios de autenticación basados en la ruta URL o cualquier
otro atributo de la request::

    public function getAuthenticationService(
        ServerRequestInterface $request
    ): AuthenticationServiceInterface {
        $service = new AuthenticationService();

        // Configuration common to both the API and web goes here.

        if ($request->getParam('prefix') == 'Api') {
            // Include API specific authenticators
        } else {
            // Web UI specific authenticators.
        }

        return $service;
    }
