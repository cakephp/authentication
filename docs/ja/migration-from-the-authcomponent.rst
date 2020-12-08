認証コンポーネントから移行
################################

違い
===========

-  このプラグインは意図的に認可を処理しません。
   `懸念事項 <https://en.wikipedia.org/wiki/Separation_of_concerns>`__ を明確に分離するために、
   わざと認可から `切り離され <https://en.wikipedia.org/wiki/Coupling_(computer_programming)>`__ ました。
   `コンピュータアクセス制御<https://en.wikipedia.org/wiki/Computer_access_control>`__ もご覧ください。
   このプラグインは、 *本人確認* と *認証のみ* を行います。
   認可のための別のプラグインがあるかもしれません。
-  セッションの自動チェックはありません。
   セッションから実際のユーザデータを取得するには、 ``SessionAuthenticator`` を使用する必要があります。
   設定したセッションキーにデータがあるかどうかをチェックして、それをIDオブジェクトに入れてくれます。
-  ユーザのデータは古いAuthComponentでは利用できなくなりましたが、
   request属性を使ってアクセスでき、
   identityオブジェクトにカプセル化されています: ``$request->getAttribute('authentication')->getIdentity();`` 。
   さらに, ``AuthenticationComponent`` の ``getIdentity()`` と ``getIdentityData()`` が使えます。
-  認証処理のロジックは、認証と本人確認に分かれています。
   認証機能はリクエストから認証情報を抽出し、識別子は認証情報を検証して一致するユーザを見つけます。
-  DigestAuthenticateの名前がHttpDigestAuthenticatorに変更しました。
-  BasicAuthenticateの名前がHttpBasicAuthenticatorに変更しました。

類似
=======

-  既存の認証アダプタ、Form、Basic、Digestはすべて残っていますが、認証アダプタにリファクタリングされています。

認証装置と認証者
==============================

関係分離の原則に従って、従来の認証オブジェクトは、
認証装置と認証者という別々のオブジェクトに分割されました。

-  **認証装置** 受信したリクエストを受け取り、そこから識別情報を抽出しようとします。
   資格情報が見つかった場合、その資格情報はユーザーの位置を示す識別子のコレクションに渡されます。
   このため、認証子はIdentifierCollectionをコンストラクタの最初の引数として取ります。
-  **認証者** ストレージシステムに対する識別資格情報を検証します。
   例: (ORMテーブル, LDAP など) と識別されたユーザーデータを返します。

これにより、必要に応じて識別ロジックを変更したり、
複数のユーザデータソースを使用したりすることが容易になります。

独自の識別子を実装したい場合は ``IdentifierInterface`` を実装しなければなりません。

認証設定の移行
========================

アプリケーションを移行する最初のステップは、
アプリケーションのブートストラップメソッドで認証プラグインをロードすることです。::

    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin('Authentication');
    }

その後、アプリケーションを更新して、認証プロバイダのインターフェースを実装してください。
これにより、AuthenticationMiddleware はアプリケーションから認証サービスを取得する方法を知ることができます。::

    // src/Application.php の中

    // 以下を追加してください
    use Authentication\AuthenticationService;
    use Authentication\AuthenticationServiceInterface;
    use Authentication\AuthenticationServiceProviderInterface;
    use Authentication\Middleware\AuthenticationMiddleware;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    // 認証インターフェースを追加します。
    class Application extends BaseApplication implements AuthenticationServiceProviderInterface
    {
        /**
         * サービスプロバイダのインスタンスを返します。
         *
         * @param \Psr\Http\Message\ServerRequestInterface $request Request
         * @param \Psr\Http\Message\ResponseInterface $response Response
         * @return \Authentication\AuthenticationServiceInterface
         */
        public function getAuthenticationService(ServerRequestInterface $request) : AuthenticationServiceInterface
        {
            $service = new AuthenticationService();
            // サービス設定 (詳細は以下をご覧ください)
            return $service;
        }
    }

次に ``AuthenticationMiddleware`` を追加します。::

    // src/Application.php のなか
    public function middleware($middlewareQueue)
    {
        // その他、エラー処理やルーティングなどの各種ミドルウェアを追加しました。

        // ミドルウェアキューにミドルウェアを追加する
        $middlewareQueue->add(new AuthenticationMiddleware($this));

        return $middlewareQueue;
    }

AuthComponent の設定の移行
------------------------------

サービスを設定する際には ``AuthComponent`` の設定配列を識別子と認証子に分割する必要があります。
また、このように ``AuthComponent`` を設定していたときは::

   $this->loadComponent('Auth', [
       'authentication' => [
           'Form' => [
               'fields' => [
                   'username' => 'email',
                   'password' => 'password',
               ]
           ]
       ]
   ]);

このように設定する必要があります。::

   // サービスのインスタンス化
   $service = new AuthenticationService();

   // 識別者の読み込み
   $service->loadIdentifier('Authentication.Password', [
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

   // 認証機能の読み込み
   $service->loadAuthenticator('Authentication.Session');
   $service->loadAuthenticator('Authentication.Form');

もし ``userModel`` をカスタマイズしているならば、以下の設定を使うことができます。::

   // サービスのインスタンス化
   $service = new AuthenticationService();

   // 識別者の読み込み
   $service->loadIdentifier('Authentication.Password', [
       'resolver' => [
           'className' => 'Authentication.Orm',
           'userModel' => 'Employees',
       ],
       'fields' => [
           'username' => 'email',
           'password' => 'password',
       ]
   ]);

以前よりも少しコードが増えていますが、認証の処理方法に柔軟性が出てきています。

ロジックアクション
--------------------

この``AuthenticationMiddleware``はあなたの認証子に基づいたアイデンティティのチェックと設定を行います。
通常、ログイン後に ``AuthComponent`` は設定した場所にリダイレクトします。
ログインが成功したときにリダイレクトするには、ログインアクションを変更して新しいIDの結果を確認してください。::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // POSTかGETにかかわらず、ユーザーがログインしている場合はリダイレクト
        if ($result->isValid()) {
            $target = $this->Authentication->getLoginRedirect();
            return $this->redirect($target);
        }

        // ユーザの送信と認証に失敗した場合にエラーを表示する
        if ($this->request->is(['post']) && !$result->isValid()) {
            $this->Flash->error('ユーザー名またはパスワードが無効です');
        }
    }

認証者の確認
-------------------

ミドルウェアを適用した後、``identity``リクエスト属性を使ってIDデータを利用することができます。
これは今使っている ``$this->Auth->user()`` の呼び出しを置き換えるものです。
現在のユーザが認証されていないか、提供された資格情報が無効な場合は、 ``identity`` が ``null`` になります。::

   $user = $request->getAttribute('identity');

認証処理の結果の詳細については、リクエストに添付されている結果オブジェクトにアクセスすることができ、
``authentication`` 属性にアクセスすることはできません。::

   $result = $request->getAttribute('authentication')->getResult();
   // 結果が有効な場合のブール値
   $isValid = $result->isValid();
   // ステータスコード
   $statusCode = $result->getStatus();
   // 識別子が何かを提供した場合のエラーメッセージまたはデータの配列
   $errors = $result->getErrors();

これまで ``AuthComponent::setUser()`` を呼んでいた場所は、
``setIdentity()`` を使うようにしてください。::

   // アクセストークンでユーザーを読み取る必要があるとします。
   $user = $this->Users->find('byToken', ['token' => $token])->first();

   // ユーザーを構成された認証子に持続させます。
   $this->Authentication->setIdentity($user);


許可/拒否ロジックの移行
--------------------------

``AuthComponent`` と同様に、
``AuthenticationComponent`` は特定の動作を簡単に '公開' し、
有効なIDを必要としないようにします。::

   // In your controller's beforeFilter method.
   $this->Authentication->allowUnauthenticated(['view']);

Each call to ``allowUnauthenticated()`` will overwrite the current
action list.

認証されていないリダイレクトの移行
===================================

By default ``AuthComponent`` redirects users back to the login page when
authentication is required. In contrast, the ``AuthenticationComponent``
in this plugin will raise an exception in this scenario. You can convert
this exception into a redirect using the ``unauthenticatedRedirect``
when configuring the ``AuthenticationService``.

You can also pass the current request target URI as a query parameter
using the ``queryParam`` option::

   // In the getAuthenticationService() method of your src/Application.php

   $service = new AuthenticationService();

   // Configure unauthenticated redirect
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

そして、コントローラのログインメソッドで ``getLoginRedirect()`` を使用して、
クエリ文字列パラメータからリダイレクト先を安全に取得することができます。::

    public function login()
    {
        $result = $this->Authentication->getResult();

        // POSTかGETに関わらず、 ユーザーがログインしている場合はリダイレクト
        if ($result->isValid()) {
            // redirect パラメータがある場合は、それを使用します。
            $target = $this->Authentication->getLoginRedirect();
            if (!$target) {
                $target = ['controller' => 'Pages', 'action' => 'display', 'home'];
            }
            return $this->redirect($target);
        }
    }

ハッシングアップグレードロジックの移行
=======================================

アプリケーションが ``AuthComponent`` のハッシュアップグレード機能を使用している場合。
このプラグインでは ``AuthenticationService`` を利用することで、そのロジックを複製することができます。::

   public function login()
   {
       $result = $this->Authentication->getResult();

       // POSTかGETに関わらず、 ユーザーがログインしている場合はリダイレクト
       if ($result->isValid()) {
           $authService = $this->Authentication->getAuthenticationService();

           // 識別子に `Password` を使用していると仮定します。
           if ($authService->identifiers()->get('Password')->needsPasswordRehash()) {
               // セーブ時にリハッシュが発生します。
               $user = $this->Users->get($this->Authentication->getIdentityData('id'));
               $user->password = $this->request->getData('password');
               $this->Users->save($user);
           }

           // ログインしたページにリダイレクトする
           return $this->redirect([
               'controller' => 'Pages',
               'action' => 'display',
               'home'
           ]);
       }
   }
