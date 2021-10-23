Middleware
##########

``AuthenticationMiddleware`` es el corazón del plugin de autenticación.
Intercepta cada solicitud a su aplicación e intenta autenticar a un usuario
con uno de los autenticadores. Cada autenticador se prueba en orden hasta
que se autentica un usuario o no se puede encontrar ningún usuario. Los atributos
de ``authentication``, ``identity`` y ``authenticationResult`` se establecen en la
solicitud que contiene la identidad si se encontró una y el objeto result de la
autenticación que puede contener errores adicionales proporcionados por los autenticadores.

Al final de cada solicitud, la ``identity`` se conserva en cada autenticador con estado,
como el autenticador ``Session``.

Configuración
=============

Toda la configuración del middleware se realiza en el ``AuthenticationService``.
En este servicio puede utilizar las siguientes opciones de configuración:

- ``identityClass`` - El nombre de clase de identidad o un constructor de identidad callable.
- ``identityAttribute`` - El atributo de solicitud utilizado para almacenar la identidad.
  Por defecto ``identity``.
- ``unauthenticatedRedirect`` - La URL para redirigir los errores no autenticados.
- ``queryParam`` - El nombre del parámetro del string de consulta que contiene
  la URL previamente bloqueada en caso de redireccionamiento no autenticado, o nulo
  para deshabilitar la adición de la URL denegada. Por defecto ``null``.


Configuración de Múltiples Configuraciones de Autenticación
===========================================================

Si su aplicación requiere diferentes configuraciones de autenticación para diferentes partes
de la aplicación, por ejemplo, la API y la interfaz de usuario web. Puede hacerlo utilizando
lógica condicional en el método hook ``getAuthenticationService()`` de sus aplicaciones. Al
inspeccionar el objeto request, puede configurar la autenticación de manera adecuada::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $path = $request->getPath();

        $service = new AuthenticationService();
        if (strpos($path, '/api') === 0) {
            // Acepta solo tokens de API
            $service->loadAuthenticator('Authentication.Token');
            $service->loadIdentifier('Authentication.Token');

            return $service;
        }

        // Autenticación web
        // Soporte de sessions y formulario login.
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form');

        $service->loadIdentifier('Authentication.Password');

        return $service;
    }

Si bien el ejemplo anterior usa un prefijo de ruta, puede aplicar una lógica similar
al subdominio, dominio o cualquier otro encabezado o atributo presente en la solicitud.
