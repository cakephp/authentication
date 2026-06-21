<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authentication\Controller\Component;

use ArrayAccess;
use ArrayObject;
use Authentication\AuthenticationServiceInterface;
use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\ImpersonationInterface;
use Authentication\Authenticator\PersistenceInterface;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\IdentityInterface;
use Cake\Controller\Component;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Http\Response;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Controller Component for interacting with Authentication.
 *
 * @implements \Cake\Event\EventDispatcherInterface<\Cake\Controller\Controller>
 */
class AuthenticationComponent extends Component implements EventDispatcherInterface
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Cake\Controller\Controller>
     */
    use EventDispatcherTrait;

    /**
     * Configuration options
     *
     * - `logoutRedirect` - The route/URL to direct users to after logout()
     * - `requireIdentity` - By default AuthenticationComponent will require an
     *   identity to be present whenever it is active. You can set the option to
     *   false to disable that behavior. See allowUnauthenticated() as well.
     * - `unauthenticatedMessage` - Error message to use when `UnauthenticatedException` is thrown.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'logoutRedirect' => false,
        'requireIdentity' => true,
        'identityAttribute' => 'identity',
        'identityCheckEvent' => 'Controller.startup',
        'unauthenticatedMessage' => null,
    ];

    /**
     * List of actions that don't require authentication.
     *
     * @var array<string>
     */
    protected array $unauthenticatedActions = [];

    /**
     * Authentication service instance.
     */
    protected ?AuthenticationServiceInterface $_authentication = null;

    /**
     * Initialize component.
     *
     * @param array<string, mixed> $config The config data.
     * @return void
     */
    public function initialize(array $config): void
    {
        $controller = $this->getController();
        $this->setEventManager($controller->getEventManager());
    }

    /**
     * Triggers the Authentication.afterIdentify event for non stateless adapters that are not persistent either
     *
     * @return void
     */
    public function beforeFilter(): void
    {
        $authentication = $this->getAuthenticationService();
        $provider = $authentication->getAuthenticationProvider();

        if (
            $provider instanceof AuthenticatorInterface &&
            !$provider instanceof PersistenceInterface &&
            !$provider instanceof StatelessInterface
        ) {
            $this->dispatchEvent('Authentication.afterIdentify', [
                'provider' => $provider,
                'identity' => $this->getIdentity(),
                'service' => $authentication,
            ], $this->getController());
        }

        if ($this->getConfig('identityCheckEvent') === 'Controller.initialize') {
            $this->doIdentityCheck();
        }
    }

    /**
     * Start up event handler
     *
     * @return void
     * @throws \Exception when request is missing or has an invalid AuthenticationService
     * @throws \Authentication\Authenticator\UnauthenticatedException when requireIdentity is true and request is missing an identity
     */
    public function startup(): void
    {
        if ($this->getConfig('identityCheckEvent') === 'Controller.startup') {
            $this->doIdentityCheck();
        }
    }

    /**
     * Returns authentication service.
     *
     * @return \Authentication\AuthenticationServiceInterface
     * @throws \Exception
     */
    public function getAuthenticationService(): AuthenticationServiceInterface
    {
        if ($this->_authentication instanceof AuthenticationServiceInterface) {
            return $this->_authentication;
        }

        $controller = $this->getController();
        $service = $controller->getRequest()->getAttribute('authentication');
        if ($service === null) {
            throw new Exception(
                'The request object does not contain the required `authentication` attribute. Verify the ' .
                'AuthenticationMiddleware has been added.',
            );
        }

        if (!($service instanceof AuthenticationServiceInterface)) {
            throw new Exception('Authentication service does not implement ' . AuthenticationServiceInterface::class);
        }

        $this->_authentication = $service;

        return $service;
    }

    /**
     * Check if the identity presence is required.
     *
     * Also checks if the current action is accessible without authentication.
     *
     * @return void
     * @throws \Exception when request is missing or has an invalid AuthenticationService
     * @throws \Authentication\Authenticator\UnauthenticatedException when requireIdentity is true and request is missing an identity
     */
    protected function doIdentityCheck(): void
    {
        if (!$this->getConfig('requireIdentity')) {
            return;
        }

        $request = $this->getController()->getRequest();
        $action = $request->getParam('action');
        if (in_array($action, $this->unauthenticatedActions, true)) {
            return;
        }

        $identity = $request->getAttribute($this->getConfig('identityAttribute'));
        if (!$identity) {
            throw new UnauthenticatedException($this->getConfig('unauthenticatedMessage', ''));
        }
    }

    /**
     * Disables the identity check for this controller and as all its actions.
     * It then doesn't require an authentication identity to be present.
     *
     * @return void
     */
    public function disableIdentityCheck(): void
    {
        $this->setConfig('requireIdentity', false);
    }

    /**
     * Set the list of actions that don't require an authentication identity to be present.
     *
     * Actions not in this list will require an identity to be present. Any
     * valid identity will pass this constraint.
     *
     * @param array<string> $actions The action list.
     * @return $this
     */
    public function allowUnauthenticated(array $actions)
    {
        $this->unauthenticatedActions = $actions;

        return $this;
    }

    /**
     * Add to the list of actions that don't require an authentication identity to be present.
     *
     * @param array<string> $actions The action or actions to append.
     * @return $this
     */
    public function addUnauthenticatedActions(array $actions)
    {
        $this->unauthenticatedActions = array_merge($this->unauthenticatedActions, $actions);
        $this->unauthenticatedActions = array_values(array_unique($this->unauthenticatedActions));

        return $this;
    }

    /**
     * Get the current list of actions that don't require authentication.
     *
     * @return array<string>
     */
    public function getUnauthenticatedActions(): array
    {
        return $this->unauthenticatedActions;
    }

    /**
     * Gets the result of the last authenticate() call.
     *
     * @return \Authentication\Authenticator\ResultInterface|null Authentication result interface
     */
    public function getResult(): ?ResultInterface
    {
        return $this->getAuthenticationService()->getResult();
    }

    /**
     * Get the identifier (primary key) of the identity.
     *
     * @return array|string|int|null
     */
    public function getIdentifier(): array|string|int|null
    {
        return $this->getIdentity()?->getIdentifier();
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @return \Authentication\IdentityInterface|null
     */
    public function getIdentity(): ?IdentityInterface
    {
        $controller = $this->getController();

        return $controller->getRequest()->getAttribute($this->getConfig('identityAttribute'));
    }

    /**
     * Returns the identity used in the authentication attempt.
     *
     * @param string $path Path to return from the data.
     * @return mixed
     * @throws \RuntimeException If the identity has not been found.
     */
    public function getIdentityData(string $path): mixed
    {
        $identity = $this->getIdentity();

        if (!$identity instanceof IdentityInterface) {
            throw new RuntimeException('The identity has not been found.');
        }

        return Hash::get($identity, $path);
    }

    /**
     * Replace the current identity
     *
     * Clear and replace identity data in all authenticators
     * that are loaded and support persistence. The identity
     * is cleared and then set to ensure that privilege escalation
     * and de-escalation include side effects like session rotation.
     *
     * @param \ArrayAccess|array $identity Identity data to persist.
     * @return $this
     */
    public function setIdentity(ArrayAccess|array $identity)
    {
        $controller = $this->getController();
        $service = $this->getAuthenticationService();

        $service->clearIdentity($controller->getRequest(), $controller->getResponse());

        /** @var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $service->persistIdentity(
            $controller->getRequest(),
            $controller->getResponse(),
            $identity,
        );

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        return $this;
    }

    /**
     * Replace the in-request identity object without persisting it.
     *
     * Use this when you only need to swap the identity attribute on the
     * current request - for example, to attach eager-loaded associations
     * or computed flags to the active user for the rest of the request -
     * without going through `clearIdentity()` and `persistIdentity()`.
     *
     * Unlike `setIdentity()`, this does not touch the session and does not
     * end an active impersonation, because no authenticator's
     * `clearIdentity()` is invoked.
     *
     * @param \ArrayAccess|array $identity Identity data or an identity object.
     * @return $this
     */
    public function replaceIdentity(ArrayAccess|array $identity)
    {
        $controller = $this->getController();
        $service = $this->getAuthenticationService();

        $identity = $service->buildIdentity($identity);

        $controller->setRequest(
            $controller->getRequest()->withAttribute(
                $service->getIdentityAttribute(),
                $identity,
            ),
        );

        return $this;
    }

    /**
     * Log a user out.
     *
     * Triggers the `Authentication.logout` event.
     *
     * @return string|null Returns null or `logoutRedirect`.
     */
    public function logout(): ?string
    {
        $controller = $this->getController();
        /** @var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $this->getAuthenticationService()->clearIdentity(
            $controller->getRequest(),
            $controller->getResponse(),
        );

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        $this->dispatchEvent('Authentication.logout', [], $controller);

        $logoutRedirect = $this->getConfig('logoutRedirect');
        if ($logoutRedirect === false) {
            return null;
        }

        return Router::normalize($logoutRedirect);
    }

    /**
     * Get the URL visited before an unauthenticated redirect.
     *
     * Reads from the current request's query string if available.
     *
     * Leverages the `unauthenticatedRedirect` and `queryParam` options in
     * the AuthenticationService.
     *
     * @param array|string|null $default Default URL to use if no redirect URL is available.
     * @return string|null
     */
    public function getLoginRedirect(array|string|null $default = null): ?string
    {
        if (is_array($default)) {
            $default = Router::url(['_base' => false] + $default);
        }

        return $this->getAuthenticationService()->getLoginRedirect($this->getController()->getRequest()) ?? $default;
    }

    /**
     * Redirect after a successful login using the validated login redirect
     * target from the current request when available.
     *
     * This is a convenience wrapper around `getLoginRedirect()` plus the
     * controller's redirect method so applications can use the plugin's
     * existing safe redirect parsing without manually reading query params.
     *
     * @param array|string $default Default URL to use when no valid login redirect is available.
     * @return \Cake\Http\Response|null
     */
    public function redirectAfterLogin(array|string $default = '/'): ?Response
    {
        $target = $this->getLoginRedirect($default) ?? $default;

        return $this->getController()->redirect($target);
    }

    /**
     * Get the Controller callbacks this Component is interested in.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.initialize' => 'beforeFilter',
            'Controller.startup' => 'startup',
        ];
    }

    /**
     * Impersonates a user
     *
     * @param \ArrayAccess $impersonated User impersonated
     * @return $this
     * @throws \Exception
     * @see https://book.cakephp.org/authentication/3/en/impersonation.html
     */
    public function impersonate(ArrayAccess $impersonated)
    {
        $service = $this->getImpersonationAuthenticationService();

        $identity = $this->getIdentity();
        if (!$identity instanceof IdentityInterface) {
            throw new UnauthenticatedException('You must be logged in before impersonating a user.');
        }
        $impersonator = $identity->getOriginalData();
        if (!($impersonator instanceof ArrayAccess)) {
            $impersonator = new ArrayObject($impersonator);
        }
        $controller = $this->getController();
        /** @var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $service->impersonate(
            $controller->getRequest(),
            $controller->getResponse(),
            $impersonator,
            $impersonated,
        );

        if (!$service->isImpersonating($controller->getRequest())) {
            throw new UnexpectedValueException('An error has occurred impersonating user.');
        }

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        return $this;
    }

    /**
     * Stops impersonation
     *
     * @return $this
     * @throws \Exception
     * @see https://book.cakephp.org/authentication/3/en/impersonation.html
     */
    public function stopImpersonating()
    {
        $service = $this->getImpersonationAuthenticationService();

        $controller = $this->getController();

        /** @var array{request: \Cake\Http\ServerRequest, response: \Cake\Http\Response} $result */
        $result = $service->stopImpersonating(
            $controller->getRequest(),
            $controller->getResponse(),
        );

        if ($service->isImpersonating($controller->getRequest())) {
            throw new UnexpectedValueException('An error has occurred stopping impersonation.');
        }

        $controller->setRequest($result['request']);
        $controller->setResponse($result['response']);

        return $this;
    }

    /**
     * Returns true if impersonation is being done
     *
     * @return bool
     * @throws \Exception
     * @see https://book.cakephp.org/authentication/3/en/impersonation.html
     */
    public function isImpersonating(): bool
    {
        if (!$this->getIdentity() instanceof IdentityInterface) {
            return false;
        }

        $service = $this->getImpersonationAuthenticationService();
        $controller = $this->getController();

        return $service->isImpersonating(
            $controller->getRequest(),
        );
    }

    /**
     * Get impersonation authentication service
     *
     * @return \Authentication\Authenticator\ImpersonationInterface
     * @throws \Exception
     * @see https://book.cakephp.org/authentication/3/en/impersonation.html
     */
    protected function getImpersonationAuthenticationService(): ImpersonationInterface
    {
        $service = $this->getAuthenticationService();
        if (!($service instanceof ImpersonationInterface)) {
            $className = $service::class;
            throw new InvalidArgumentException(
                sprintf('The %s must implement ImpersonationInterface in order to use impersonation.', $className),
            );
        }

        return $service;
    }
}
