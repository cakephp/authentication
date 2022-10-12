Quick Start
###########

CakePHPから `composer <https://getcomposer.org/>`_ でプラグインをインストールします。
プロジェクトのルートディレクトリ( **composer.json** ファイルが置かれている場所です)

.. code-block:: bash

    php composer.phar require "cakephp/authentication:^2.0"


プロジェクトの ``src/Application.php``  に以下の文を追加してプラグインをロードしてください。 ::

    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authentication');
    }


はじめに
===============

認証プラグインは、ミドルウェアとしてアプリケーションと統合します。 `middleware <https://book.cakephp.org/4/en/controllers/middleware.html>`_
また、認証されていないアクセスをより簡単にするためのコンポーネントとして使用することもできます。  まずはミドルウェアを適用してみましょう。

**src/Application.php** に以下のクラスを追加します。

インポート::

    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Identifier\IdentifierInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Cake\Http\MiddlewareQueue;
    use Psr\Http\Message\ServerRequestInterface;

次に、アプリケーションに実装されたインターフェースに ``AuthenticationProviderInterface`` を追加します。::

    class Application extends BaseApplication implements AuthenticationServiceProviderInterface

次に、 ``middleware()`` メソッドに以下の `AuthenticationMiddleware` を追加します。::

    $middleware->add(new AuthenticationMiddleware($this));

.. note::
    両方ある場合は ``AuthenticationMiddleware`` の前に  ``AuthorizationMiddleware`` を追加するようにしてください。

リクエストの処理を開始すると、 ``AuthenticationMiddleware`` はフックメソッドを呼び出します。
このフックメソッドにより、アプリケーションが使用したい ``AuthenticationService`` を定義することができます。
以下のメソッドを **src/Application.php** に記述します。::

    /**
     * サービスプロバイダのインスタンスを返します。
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();

        // 認証されていない場合にユーザーがどこにリダイレクトするかを定義します。
        $service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $fields = [
            IdentifierInterface::CREDENTIAL_USERNAME => 'email',
            IdentifierInterface::CREDENTIAL_PASSWORD => 'password'
        ];

        // 認証者を読み込みます。セッションを優先してください。
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => '/users/login'
        ]);

        // 識別子を読み込みます。
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        return $service;
    }


まず、ユーザーが認証されていない場合にどうするかを設定します。
次に、アプリケーションがユーザーを認証するための仕組みを定義する ``Session`` と ``Form`` :doc:`/authenticators` をアタッチします。
``Session`` はセッション内のデータに基づいてユーザを識別し、 ``Form`` はログインフォームを ``loginUrl`` で扱うことを可能にします。

最後に、ログインしたユーザーを表す :doc:`identifier </identifiers>` に変換するための :doc:`identity </identity-object>` をアタッチします。

認証が確認できた場合、ミドルウェアは認証サービスを `属性 <https://www.php-fig.org/psr/psr-7/>`_. としてリクエストオブジェクトに追加します。

次に、 ``AppController`` に :doc:`/authentication-component` を呼び出します。::

    // in src/Controller/AppController.php
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
    }

デフォルトでコンポーネントは、 **全て** のアクションに認証済みのユーザーを必要とします。
特定のコントローラでこの動作を無効にするには、 ``allowUnauthenticated()`` を使用してください。::

    // コントローラの beforeFilter か initialize で、
    // ログインしている必要のないview()とindex()を作成します。
    $this->Authentication->allowUnauthenticated(['view', 'index']);

ログインアクションの構成
========================================

アプリケーションにミドルウェアを適用したら、ユーザーがログインするための方法が必要になります。
基本的に次のように ``UsersController`` にloginアクションと追加します。::

    public function login()
    {
        $result = $this->Authentication->getResult();
        // ユーザーがログインしている場合は、そのユーザーを送り出してください。
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect() ?? '/home';
            return $this->redirect($target);
        }
        if ($this->request->is('post')) {
            $this->Flash->error('ユーザー名とパスワードが無効です');
        }
    }

前のセクションで述べたように、コントローラの ``beforeFilter()`` コールバックで ``login`` アクションにアクセスできるようにして、
認証されていないユーザがアクセスできるようにしてください。::

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login']);
    }

シンプルなlogoutアクションの追加::

    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

ログインするためには、ユーザーはハッシュ化されたパスワードを持つ必要があります。以下のようなことができます。
ユーザーがエンティティを使用してパスワードを更新すると、自動的にパスワードをハッシュ化します。::

    // in src/Model/Entity/User.php
    use Authentication\PasswordHasher\DefaultPasswordHasher;

    class User extends Entity
    {
        // ... other methods

        // Automatically hash passwords when they are changed.
        protected function _setPassword(string $password)
        {
            $hasher = new DefaultPasswordHasher();
            return $hasher->hash($password);
        }
    }


Further Reading
===============

* :doc:`/authenticators`
* :doc:`/identifiers`
* :doc:`/password-hashers`
* :doc:`/identity-object`
* :doc:`/authentication-component`
* :doc:`/migration-from-the-authcomponent`
* :doc:`/url-checkers`
* :doc:`/testing`
* :doc:`/view-helper`
