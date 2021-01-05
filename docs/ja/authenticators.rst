認証機能
##############

Authenticatorsは、リクエストデータを認証に変換する処理を行います。
それらは、 :doc:`/identifiers` を利用して、既知の :doc:`/identity-object` を見つけます。

セクション
===========

この認証機能は、セッションにユーザーデータや資格情報が含まれているかどうかをチェックします。
以下に挙げた ``Form`` のようなステートフルな認証機能を使う場合は、
一度ログインしたユーザのデータがそれ以降のリクエストでセッションから取得されるように、
最初に ``Session`` の認証機能をロードするようにしてください。
設定オプション:

-  **sessionKey**: ユーザーデータのセッションキー, デフォルトは ``Auth``
-  **identify**:  bool ``true``` の値を指定してこのキーを設定すると、
   セッションの認証情報を識別子と照合できるようになります。
   ``true`` の場合、設定された :doc:`/identifiers` はリクエストのたびにセッションに
   保存されたデータを使ってユーザを識別するために使われます。デフォルト値は ``false``.
-  **fields**: ``username`` フィールドをユーザストレージ内の一意の識別しに写像することができます。
   デフォルトは ``username``です。
   このオプションは``identify`` オプションが true に設定されている場合に使用されます.

フォーム
=========

リクエストボディのデータを調べます。通常、フォームの送信が POST / PUT 経由で行われる場合が多いです。

設定オプション:

-  **loginUrl**:ログインURL、文字列またはURLの配列。デフォルトは``null`` で、
   すべてのページがチェックされます。
-  **fields**: ``username`` と ``password`` を指定したPOSTフィールドに描画する配列です。
-  **urlChecker**: URLチェッカーのクラスまたはオブジェクト。デフォルトは ``DefaultUrlChecker``。
-  **useRegex**: URLのマッチングに正規表現を使うかどうか。 デフォルトは ``false``.
-  **checkFullUrl**: クエリ文字を含むURLをチェックするかどうか。
   ログインフォームが別のサブドメインにある場合に便利です。
   デフォルトは、``false``。


.. warning::
    URLに配列構文を使用した場合、URLはCakePHPルータによって生成されます。
    結果は、ルート処理によってはリクエストURIに実際にあるものと **異なる** かもしれない。
    大文字小文字を区別するために、これを考慮してください

トークン
========

トークン認証機能は、ヘッダやリクエストパラメータの中にある
リクエストと一緒に来るトークンに基づいてリクエストを認証することができます。

設定オプション:

-  **queryParam**: クエリパラメータの名前. クエリパラメータからトークンを取得したい場合に設定します。
-  **header**: ヘッダーの名前. ヘッダーからトークンを取得したい場合に設定します。
-  **tokenPrefix**: オプションのトークンプレフィックス。

ヘッダやクエリ文字列からトークンを取得する例は次のようになります::

    $service->loadAuthenticator('Authentication.Token', [
        'queryParam' => 'token',
        'header' => 'Authorization',
        'tokenPrefix' => 'Token'
    ]);

上記の場合、 ``Token`` の前にトークンとスペースがある限り、 ``token`` のGETパラメータまたは ``Authorization`` ヘッダが読み込まれます。

トークンは常に次のように設定された識別子に渡されます::

    [
        'token' => '{token-value}',
    ]

JWT
===

JWT 認証機能は、ヘッダまたはクエリパラメータから `JWT token <https://jwt.io/>`__ を取得し、
ペイロードを直接返すか、識別子に渡して別のデータソースなどと照合して検証します。

-  **header**: トークンを確認するためのヘッダ行です。デフォルトは ``Authorization`` です。
-  **queryParam**: トークンをチェックするクエリパラメータ。デフォルトは ``token`` です。
-  **tokenPrefix**: prefixトークン. デフォルトは ``bearer`` です。
-  **algorithms**: Firebase JWT用のハッシュアルゴリズムの配列。デフォルトは配列 ``['HS256']`` です。
-  **returnPayload**:識別子を経由せずに、トークンのペイロードを直接返すか返さないか。デフォルトは ``true`` です。
-  **secretKey**: デフォルトは ``null`` ですが、秘密鍵を ``Security::salt()`` 提供しているCakePHPアプリケーションのコンテキストではない場合は **必須** です。

デフォルトでは、 ``JwtAuthenticator`` は対称鍵アルゴリズム ``HS256`` を使用し、暗号化鍵として、
``Cake\Utility\Security::salt()``  の値を使用します。::

    # 秘密鍵生成
    openssl genrsa -out config/jwt.key 1024
    # 公開鍵生成
    openssl rsa -in config/jwt.key -outform PEM -pubout -out config/jwt.pem

``jwt.key`` ファイルは秘密鍵であり、安全に保管する必要があります。
``jwt.pem`` ファイルは公開鍵です。
このファイルは、モバイルアプリなどの外部アプリケーションによって作成されたトークンを
検証する必要がある場合に使用する必要があります。

以下の例では、 ``JwtSubject`` 識別しを用いてトークンの ``sub`` (subject) に基づいてユーザーを識別し、
トークンの検証に公開鍵を使用するように、 ``Authenticator`` を設定しています。

``Application`` クラスに以下を追加してください::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        // ...
        $service->loadIdentifier('Authentication.JwtSubject');
        $service->loadAuthenticator('Authentication.Jwt', [
            'secretKey' => file_get_contents(CONFIG . '/jwt.pem'),
            'algorithms' => ['RS256'],
            'returnPayload' => false
        ]);
    }

``UsersController`` に追加::

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

公開鍵ファイルを外部アプリに共有する以外にも、
以下のようにアプリを設定することで、
JWKSのエンドポイントを経由して公開鍵ファイルを配布することができます。::

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

JWKSの詳細情報は https://tools.ietf.org/html/rfc7517
または https://auth0.com/docs/tokens/concepts/jwks を参照してください。

Http基本
=========

https://en.wikipedia.org/wiki/Basic_access_authentication を確認してください。

設定オプション:

-  **realm**: デフォルトは ``$_SERVER['SERVER_NAME']``  で、必要に応じて上書きしてください。

Httpダイジェスト
=================

https://en.wikipedia.org/wiki/Digest_access_authentication を確認してください。

設定オプション:

-  **realm**: デフォルトは ``null`` です。
-  **qop**: デフォルトは ``auth`` です。
-  **nonce**: デフォルトは ``uniqid(''),`` です。
-  **opaque**: デフォルトは ``null`` です。

クッキー認証機能 別名 "リメンバーミー"
======================================

クッキー認証機能を使用すると、ログインフォームに "remember me "機能を実装することができます。

ログインフォームに、この認証機能で設定されているフィールド名と一致するフィールドがあることを確認してください。

クッキーを暗号化して複合化するには、AuthenticationMiddlewareの **前に**
EncryptedCookieMiddlewareをアプリに追加したことを確認してください。

設定オプション:

-  **rememberMeField**: デフォルトは ``remember_me`` です。
-  **cookie**: クッキーオプションの配列:

   -  **name**: クッキー名, デフォルトは ``CookieAuth``
   -  **expire**: 有効期限, デフォルトは ``null`` です。
   -  **path**: パス, デフォルトは ``/`` です。
   -  **domain**: ドメイン, デフォルトは空の文字列です \`\`
   -  **secure**: Bool, デフォルトは ``false`` です。
   -  **httpOnly**: Bool, デフォルトは ``false`` です。
   -  **value**: Value, デフォルトは空の文字列です。 \`\`

-  **fields**: ``username`` と ``password`` を指定されたIDフィールドにマップする配列
-  **urlChecker**: URLチェッカーのクラスまたはオブジェクト。デフォルトは ``DefaultUrlChecker``
-  **loginUrl**: ログイン URL, 文字列または URL の配列。 デフォルトは ``null`` で、すべてのページがチェックされます。
-  **passwordHasher**: トークンハッシュに使うパスワードハッシャー。デフォルトは ``DefaultPasswordHasher::class``.

OAuth
=====

現在のところ、OAuth認証機能の実装予定はありません。
その主な理由は、OAuth 2.0が認証プロトコルではないからです。

このトピックについて知りたい場合は、
`ここ <https://oauth.net/articles/authentication/>`__.

将来的にはOpenID Connect認証機能を追加するかもしれません。

イベント
==========

認証によって発生するイベントは1つだけです。:
``Authentication.afterIdentify``.

イベントとは何か、イベントの使い方がわからない場合は
`ここをクリックしてください！ <https://book.cakephp.org/3.0/en/core-libraries/events.html>`__.

身元の特定に成功した後に ``Authentication.afterIdentify`` イベントが
``AuthenticationComponent`` によって発行されます。

イベントには以下のデータが含まれています。:

-  **provider**: ``\Authentication\Authenticator\AuthenticatorInterface`` を実装したオブジェクトです。
-  **identity**: ``\ArrayAccess`` を実装したオブジェクトです。
-  **service**: ``\Authentication\AuthenticationServiceInterface`` を実装したオブジェクトです。

イベントのサブジェクトは、AuthenticationComponent がアタッチされている
現在のコントローラのインスタンスになります。

しかし、このイベントが発生するのは、IDを識別するために使用された
authenticator が永続的ではなく、ステートレスではない場合に限られます。
これは、例えばセッション認証やトークンがリクエストのたびに毎回イベントを発生させてしまうからです。

含まれている認証子からは、FormAuthenticatorのみがイベントを発生させます。
その後、セッション認証機能がIDを提供します。

URL チェッカー
=================

``Form`` や ``Cookie`` のような認証証は、 ``/login`` ページのような
特定のページでのみ実行されるべきものがあります。
これは、URLチェッカーを使用することで実現できます。

デフォルトは ``DefaultUrlChecker`` を使います。
これは、正規表現チェックをサポートした文字列URLを比較に使用します。

設定オプション:

-  **useRegex**: URLのマッチングに正規表現を使用するかどうか。デフォルトは ``false`` です。
-  **checkFullUrl**: フルURLをチェックするかどうか。
   ログインフォームが別のサブドメインにある場合に便利です。
   デフォルトは ``false`` です。

フレームワーク固有の URL のサポートが必要な場合など、カスタム URL チェッカーを実装することができます。
この場合は ``Authentication\UrlChecker\UrlCheckerInterface`` を実装してください。

もっと詳しくURLチェッカーについて知るには :doc:`このページを見てください </url-checkers>`.

成功した Authenticator または Identifier の取得
==================================================

ユーザーの認証が完了した後、ユーザーの認証に成功した Authenticator を確認したり、
次のような操作を行うことができます。::

    // コントローラー、アクションの中
    $service = $this->request->getAttribute('authentication');

    // 認証に失敗した場合、または認証機能がある場合は null になります。
    $authenticator = $service->getAuthenticationProvider();

ユーザーを特定した識別子も取得できます。::

    // コントローラー、アクションの中
    $service = $this->request->getAttribute('authentication');

    //  認証に失敗した場合は null になります。
    $identifier = $service->getIdentificationProvider();


ステートフル認証でステートレス認証を使用する
==================================================

``Token`` や ``HttpBasic`` を使用している場合は、他の認証しと一緒に ``HttpDigest`` を使用します。
これらの認証子は、認証証明書が見つからないか無効な場合にリクエストを停止することを覚えておくべきです。
これは、これらの認証子がレスポンスの中で特定のチャレンジヘッダを送信しなければならないために必要です::

    use Authentication\AuthenticationService;

    // サービスのインスタンス化
    $service = new AuthenticationService();

    // 識別子の読み込み
    $service->loadIdentifier('Authentication.Password', [
        'fields' => [
            'username' => 'email',
            'password' => 'password'
        ]
    ]);
    $service->loadIdentifier('Authentication.Token');

    // Basicを最後にして、認証子をロードします。
    $service->loadAuthenticator('Authentication.Session');
    $service->loadAuthenticator('Authentication.Form');
    $service->loadAuthenticator('Authentication.HttpBasic');

もし ``HttpBasic`` や ``HttpDigest``  と他の認証子を組み合わせたい場合は、
これらの認証子はリクエストを中止してブラウザのダイアログを強制的に表示するので注意してください。

認証されていないエラーの処理
================================

認証されていないユーザがいた場合、 ``AuthenticationComponent`` は例外を発生させます。
この例外をリダイレクトに変換するには ``AuthenticationService`` を設定する際に
``unauthenticatedRedirect`` を使ってください。

また、 ``queryParam`` オプションを使って現在のリクエストのターゲットURIを
クエリパラメータとして渡すこともできます::

   // src/Application.phpのgetAuthenticationService() メソッドの中

   $service = new AuthenticationService();

   // 認証されていないときにリダイレクトする
   $service->setConfig([
       'unauthenticatedRedirect' => '/users/login',
       'queryParam' => 'redirect',
   ]);

そして、コントローラのログインメソッドの中で``getLoginRedirect()``
を使うことで、クエリ文字列パラメータ:からリダイレクト先を安全に取得することができます。::

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
