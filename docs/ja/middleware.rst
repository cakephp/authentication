Middleware
##########

``AuthenticationMiddleware`` は認証プラグインの中で成形しています。
これは、アプリケーションへの各リクエストを捕らえ、いずれかの認証証明証でユーザーの認証を試みます。
各認証機能は、ユーザが認証されるまで、あるいはユーザが見つからないまで順番に試行されます。
認証された場合の ID と認証結果オブジェクトを含むリクエストには ``authentication`` 、``identity`` 、 ``authenticationResult``
属性が設定され、認証子によって提供された追加のエラーを含むことができます。

各リクエストの最後に ``identity`` は ``Session`` のようなステートフルな認証機能に保持されます。

設定
=========

ミドルウェアの設定は全て ``AuthenticationService`` で行います。
サービスでは、次の構成オプションを使用できます:

- ``identityClass`` - IDのクラス名、または呼び出し可能なIDビルダー。
- ``identityAttribute`` - ID を格納するために使用されるリクエスト属性。デフォルトは ``identity``。
- ``unauthenticatedRedirect`` - 認証されていない場合リダイレクトするURL。
- ``queryParam`` - 文字列を設定すると、認証されていないリダイレクトに
  ``redirect`` クエリ文字列パラメータに以前にブロックされたURLが含まれるようになります。


複数の認証設定の設定
=========================

アプリケーションがAPIやWeb UIなど、アプリケーションのさまざまな部分で異なる認証設定を必要とする場合。
これはアプリケーションの ``getAuthenticationService()`` フックメソッドで条件付きロジックを使用することで可能です。
リクエストオブジェクトを検査することで、認証を適切に設定することができます::

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $path = $request->getPath();

        $service = new AuthenticationService();
        if (strpos($path, '/api') === 0) {
            // Accept API tokens only
            $service->loadAuthenticator('Authentication.Token');
            $service->loadIdentifier('Authentication.Token');

            return $service;
        }

        // Web 認証
        // サポートセッションとフォームログイン
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form');

        $service->loadIdentifier('Authentication.Password');

        return $service;
    }

上記の例ではパスプレフィックスを使用していますが、同様のロジックをサブドメインやドメイン、
リクエストに存在するその他のヘッダや属性にも適用することができます。
