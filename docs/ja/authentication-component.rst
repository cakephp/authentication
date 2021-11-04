認証 Component
===================

認証結果にアクセスするために ``AuthenticationComponent`` にアクセスすることができます。
ユーザーの身元とログアウトユーザーを取得できます。
他のコンポーネントと同じように ``AppController::initialize()`` でコンポーネントをロードします ::

    $this->loadComponent('Authentication.Authentication', [
        'logoutRedirect' => '/users/login'  // デフォルトはfalse
    ]);

一旦ロードされると、全てのアクションが認証済みユーザーでしか入れなくなります。
しかし、他のアクセス制御チェックは行わないでください。
このチェックを無効にするには ``allowUnauthenticated()``を使います::

    // beforeFilter メソッドの中に記述してください。
    $this->Authentication->allowUnauthenticated(['view']);

ログインしているユーザーへのアクセス
--------------------------------------

認証されたユーザーデータを取得するには認証コンポーネントのこちらを使用します ::

    $user = $this->Authentication->getIdentity();

リクエストインスタンスから直接ユーザーデータ を取得することもできます。::

    $user = $request->getAttribute('identity');

ログイン状態を確認する
-------------------------

認証処理が成功したかどうかは、結果オブジェクトにアクセスすることで確認できます。::

    // 認証コンポーネントを使います。
    $result = $this->Authentication->getResult();

    // リクエストオブジェクトを使います。
    $result = $request->getAttribute('authentication')->getResult();

    if ($result->isValid()) {
        $user = $request->getAttribute('identity');
    } else {
        $this->log($result->getStatus());
        $this->log($result->getErrors());
    }

結果セットは ``getStatus()`` から返されたオブジェクトの状態が、結果オブジェクトの中のこれらの定数のいずれかと一致します。:

* ``ResultInterface::SUCCESS``, うまくいった場合。
* ``ResultInterface::FAILURE_IDENTITY_NOT_FOUND``, 身元が不明の場合。
* ``ResultInterface::FAILURE_CREDENTIALS_INVALID``, クレデンシャルが無効な場合。
* ``ResultInterface::FAILURE_CREDENTIALS_MISSING``, クレデンシャルがリクエストに含まれていない場合。
* ``ResultInterface::FAILURE_OTHER``, その他の種類の障害が発生した場合。

``getErrors()`` が返すエラー配列には、
認証を試みた特定のシステムから得られる **追加の** 情報が含まれています。
例えば、LDAPやOAuthなどは、その実装に特有のエラーをここに書き込むことで、
原因のロギングやデバッグを容易にすることができます。
しかし、同梱されている認証子のほとんどはここには何も入れていません。

identity のログアウト
------------------------

ログアウトするには::

    $this->Authentication->logout();

もし、 ``logoutRedirect`` を設定しているならば、
``Authentication::logout()`` はその値を返します。
それ以外の場合は、 ``false`` を返します。
どちらの場合も実際のリダイレクトは行われません。

あるいは、 コンポーネントの代わりに、サービスを使ってログアウトすることもできます ::

    $return = $request->getAttribute('authentication')->clearIdentity($request, $response);

返される結果には、次のような配列が含まれます。::

    [
        'response' => object(Cake\Http\Response) { ... },
        'request' => object(Cake\Http\ServerRequest) { ... },
    ]

.. note::
    これはリクエストオブジェクトとレスポンスオブジェクトを含む配列を返します。
    両方とも不変なので、新しいオブジェクトを取り戻すことができます。
    変更されたレスポンスやリクエストオブジェクトを使い続けたい場合は、
    作業しているコンテキストに応じて、今後はこれらのインスタンスを使用しなければならないでしょう。

自動Identityチェックを構成する
---------------------------------

デフォルトでは ``認証コンポーネント`` は、 ``Controller.initialize``
イベントの間に存在するIDを自動的に強制します。
このチェックは ``Controller.startup`` イベント中に適用することもできます::

    // コントローラの initialize() メソッドの中です。
    $this->loadComponent('Authentication.Authentication', [
        'identityCheckEvent' => 'Controller.startup',
    ]);

また、 ``requireIdentity`` オプションを使って ID チェックを完全に無効にすることもできます。
