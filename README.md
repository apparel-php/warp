# Warp - Web Application Routing Preprocessor

Warp は、PHP の Web フレームワーク **Woof** のためのルーティング機構を提供する拡張ライブラリです。

オブジェクト指向の原則に基づいた、堅牢で型安全なルーティング機構が最大の特徴です。
他の主要フレームワークのルーティング機構に見られるような、リフレクションやマジックメソッドなどの「黒魔術」を排し、
`RoutingContext` (処理経過をあらわすオブジェクト) と `Resolver` (URL の解決を担当するクラス)
の連携によって、予測可能でスケーラブルなアプリケーション設計を実現します。

> **Note:** Warp は Woof の API に依存する拡張ライブラリであり、単体では動作しません。
また、Woof は Warp の使用を強制するものではなく、小規模なアプリケーションであれば独自のルーティングをスクラッチで実装することも、
別のルーティングライブラリを採用することも可能です。

## 特徴

* **型安全なコントローラ指定:**
    * 対象の Controller をクラス名ベースで指定するのではなく、実際のインスタンスを指定することで決定します。
    クラス名の記述ミスや型のミスマッチによる実行時エラーを防げるだけでなく、ルーティング処理の過程できめ細やかな Controller インスタンス生成を行うことが可能です。
* **セグメント単位の処理:**
    * 深い階層の URL を、セグメント (URL を `/` で分割した部分文字列) ごとに下位の Resolver
    へ移譲していく設計により、クラスの肥大化を防ぎます。
    たとえば `/admin/posts/` や `/admin/config/` のように頭が "/admin/" で始まる URL の解決をすべて
    AdminResolver に任せる、という具合にセグメント単位で Resolver を設計することができます。
* **動的パラメータのマッチング:**
    * `/{id}/edit/` のような動的パラメータを `PathPattern` によって安全に抽出し、直感的なマッチングを可能にします。
* **責任の分離:**
    * Resolver は「宛先の決定」のみに専念し、データベースアクセスなどのビジネスロジックは Controller の実行フェーズに遅延させる設計を推奨しています。

## 基本方針

Warp のルーティングは、以下の 4 つのインタフェースまたはクラスで構成されます。

1. **`RoutingContext`**: 現在のリクエストパスや、未処理のパスセグメントを保持するコンテキストです。
2. **`Resolver`**: コンテキストを評価し、実行すべき `Controller` を `Target` として返します。
3. **`Target`**: Resolver の処理結果です。実行すべき Controller が見つかった場合は `Target::found()`, 見つからなかった場合は `Target::empty()` でインスタンス化します。
4. **`Controller`**: Web アプリケーションの実際の処理を行い、結果を `Response` として返します。(Woof 内の API)

Warp のルーティングの仕組みは、DNS の名前解決においてルートサーバーから下位のネームサーバーへと解決を移譲していくシステムに似ています。
巨大なルーティング定義を 1 箇所に集約するのではなく、パスの階層ごとに担当する `Resolver` を定義し、
責任をリレーしていくことで見通しと保守性を高く保ちます。

---

## ルーティング API の基本

Warp のルーティング機構を利用する上で前提となる「セグメント」の概念と、
`RoutingContext` が提供する主要 API について説明します。

* **セグメントの分割:** URL のパスは `/` を区切りとして「セグメント」という単位に分割されます。例えば `/foo/bar/baz.html` の場合、`"foo"`, `"bar"`, `"baz.html"` の 3 セグメントに分割されます。
* **末尾のスラッシュの扱い:** 末尾が `/` で終わる URL については、最後のセグメントが空文字列になります。例えば `/foo/bar/baz/` の場合は分割結果が `"foo"`, `"bar"`, `"baz"`, `""` の 4 セグメントとなります。
* **末尾スラッシュによる厳密な区別:** このルーティング機構は末尾の `/` のあり・なしを厳密に区別します。末尾が `/` で終わる場合、その直前のセグメントがディレクトリであり、そのディレクトリ直下にある `index.html` などのインデックスページにあたる URL が指定されたものと解釈されます。

これらの前提を踏まえ、Resolver 内で以下のメソッドを活用してルーティングを行います。

* **`shift()`**: 現在の `RoutingContext` が参照しているセグメントを 1 つ進めます。例えば `/foo/bar/baz.html` を解釈しているとき、初期状態では `"foo"` を参照していますが、`shift()` を実行するたびに参照先が `"bar"`, `"baz.html"` へと推移していきます。
* **`peek()`**: 現在参照している先頭のセグメントをチェック (取得) するときに使います。
* **`getPartialPath()`**: 現在参照しているセグメント以降の URL を完全一致でマッチしたい場合に使います。主に URL の末尾部分をチェックして、最終的な `Controller` を決定したいときに使用します。
* **`matchPath()`**: URL の一部に動的なパラメータ (例: `/var/baz/{id}.html` の `{id}` の部分) を含む場合のマッチングと、そのパラメータの抽出を行います。

---

## 使い方 (サンプルアプリケーション)

以下のような URL 構成を持つオウンドメディアおよびその管理画面システムを例に、Warp の実装パターンを紹介します。

* `/` (トップページ)
* `/articles/` (記事一覧)
* `/articles/{id}/` (記事詳細)
* `/privacy-policy.html` (プライバシーポリシー)
* `/sitemap.html` (サイトマップ)
* `/admin/` (管理画面ダッシュボード)
* `/admin/login/` (ログイン)
* `/admin/posts/` (管理画面: 記事一覧)
* `/admin/posts/{id}/` (管理画面: 記事詳細確認)
* `/admin/posts/{id}/edit/` (管理画面: 記事編集フォーム・更新)
* `/admin/posts/{id}/remove/` (管理画面: 記事削除)
* `/admin/config/` (設定ページ)

### 1. フロントコントローラ (`index.php`)

アプリケーションの起点となる `index.php` の構成例です。
HTTP リクエストを受け取り、`RootResolver` を起点として解決された Controller を実行します。

```php
<?php

$appRoot = dirname(__DIR__);
require_once "{$appRoot}/vendor/autoload.php";

use Woof\Web\WebEnvironmentBuilder;
use Woof\Http\ResponseBuilder;
use Warp\RoutingContext;
use App\Resolver\RootResolver;

// アプリケーション環境の構築
$env = (new WebEnvironmentBuilder())
    ->setConfigDir("{$appRoot}/config")
    ->setResourcesDir("{$appRoot}/resources")
    ->build();

// HTTP リクエストの読み込み
$request = $env->getClientRequest();

// ルーティングコンテキストの生成
$context = RoutingContext::create($request, $env);

// 最上位の Resolver を構築して実行
$rootResolver = new RootResolver();
$target       = $rootResolver->resolve($context);

// 解決された Controller を取得し、リクエストを処理する
$controller = $target->unwrapOr(function () {
    // 見つからなかった場合は NotFoundController を採用する
    return new NotFoundController();
});
$response   = $controller->handle($request, $env);

// HTTP レスポンスの送信
$output = new DefaultOutput();
$output->send($response);
```

### 2. RootResolver (アプリケーションの入り口)

アプリケーションのルート階層を担当する Resolver です。静的なパスを完全一致 (`getPartialPath()`)
で解決しつつ、特定のディレクトリへのアクセスは専用の子 Resolver へ移譲 (`shift()`) します。

```php
namespace App\Resolver;

use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;

class RootResolver implements Resolver
{
    public function resolve(RoutingContext $context): Target
    {
        $path = $context->getPartialPath();

        // URL: /
        if ($path === "/") {
            return Target::found(new TopController());
        }
        // URL: /privacy-policy.html
        if ($path === "/privacy-policy.html") {
            return Target::found(new PrivacyPolicyController());
        }
        // URL: /sitemap.html
        if ($path === "/sitemap.html") {
            return Target::found(new SitemapController());
        }

        // 下位の Resolver に処理を移譲
        $segment = $context->peek();

        // URL: /articles/... 配下
        if ($segment === "articles") {
            return (new ArticlesResolver())->resolve($context->shift());
        }

        // URL: /admin/... 配下
        if ($segment === "admin") {
            return (new AdminResolver())->resolve($context->shift());
        }

        // 存在しなかった場合
        return Target::empty();
    }
}
```

### 3. AdminResolver (管理画面のルーティング)

移譲された `AdminResolver` では、管理画面固有の機能をルーティングします。
余分なセグメント (例: `/admin/login/extra`) が存在した場合は 404 扱いにする必要があるため、
完全一致 (`getPartialPath()`) でマッチングを行います。
さらに深い `/admin/posts/` 以下の階層の処理については、別の子 Resolver へと分割します。

```php
namespace App\Resolver\Admin;

use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;

class AdminResolver implements Resolver
{
    public function resolve(RoutingContext $context): Target
    {
        $path = $context->getPartialPath();

        // 完全一致でのルーティング
        // URL: /admin/
        if ($path === "/") {
            return Target::found(new DashboardController());
        }
        // URL: /admin/login/
        if ($path === "/login/") {
            return Target::found(new LoginController());
        }
        // URL: /admin/config/
        if ($path === "/config/") {
            return Target::found(new ConfigController());
        }

        // URL: /admin/posts/... 配下
        $segment = $context->peek();

        if ($segment === "posts") {
            return (new AdminPostsResolver())->resolve($context->shift());
        }

        return Target::empty();
    }
}
```

### 4. 動的パラメータのマッチングと前処理の分離

`/admin/posts/{id}/...` のような各種 CRUD 操作では、Warp の `matchPath()` を利用してプレースホルダを抽出します。
Warp では Resolver 内で直接データベースへのアクセス (エンティティの存在確認) を行わず、
前処理用のコントローラー(今回のサンプルでは `PostItemActionController`) に解決させることで、
ルーティングのパフォーマンスと保守性を高める設計を推奨しています。

```php
namespace App\Resolver\Admin;

use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;

class AdminPostsResolver implements Resolver
{
    public function resolve(RoutingContext $context): Target
    {
        // URL: /admin/posts/ (記事一覧)
        if ($context->getPartialPath() === "/") {
            return Target::found(new PostListController());
        }

        // URL: /admin/posts/{id}/edit/, /admin/posts/{id}/remove/ など (記事の各操作)
        $match = $context->matchPath("/{id}/{action}/");
        if ($match->isMatched()) {
            return Target::found(new PostItemActionController($match->get("id"), $match->get("action")));
        }

        // URL: /admin/posts/{id}/ (記事の詳細確認)
        $matchView = $context->matchPath("/{id}/");
        if ($matchView->isMatched()) {
            return Target::found(new PostItemActionController($matchView->get("id"), "view"));
        }

        return Target::empty();
    }
}
```

### 5. 前処理コントローラーでのエンティティ取得と分岐

`PostItemActionController` は、リクエストの実行フェーズ (`handle()`) で初めてデータベースからエンティティを取得します。
取得できなかった場合は 404 Not Found のレスポンスを返し、存在した場合は `switch` 文を用いて対象の具象コントローラーへ委譲します。
このパターンを用いることで、共通処理 (存在確認など) をシンプルにまとめることができます。

```php
namespace App\Controller\Admin;

use Woof\Http\Request;
use Woof\Web\WebEnvironment;
use Woof\Web\Controller;

class PostItemActionController implements Controller
{
    private $id;
    private $action;

    public function __construct(string $id, string $action)
    {
        $this->id     = $id;
        $this->action = $action;
    }

    public function handle(Request $req, WebEnvironment $env)
    {
        // 1. 実行フェーズでエンティティを取得 (DB アクセス用のクラスが別途実装されている前提)
        $postStorage = new PostStorage($env);
        $post        = $postStorage->get($this->id);

        // 2. 共通の前処理: 存在しない場合は 404 Not Found
        if ($post === null) {
            return (new NotFoundController())->handle($req, $env);
        }

        // 3. アクションに応じたコントローラの生成と委譲
        switch ($this->action) {
            case "view":
                return (new PostViewController($post))->handle($req, $env);
            case "edit":
                return (new PostEditController($post))->handle($req, $env);
            case "remove":
                return (new PostRemoveController($post))->handle($req, $env);
            default:
                // 想定外の action が指定された場合は 404 Not Found
                return (new NotFoundController())->handle($req, $env);
        }
    }
}
```

---

## Tips: URL の正規化 (CanonicalPathResolver)

Warp は末尾のスラッシュの有無や連続するスラッシュを厳密に区別するため、
ユーザーの URL 指定の表記ブレによって意図しない 404 Not Found が発生する可能性があります。
URL のブレを吸収して安全にルーティングを行いたい場合は、起点となる Resolver を
`CanonicalPathResolver` でラップすると便利です。

```php
use Warp\Standard\CanonicalPathResolver;
use App\Resolver\RootResolver;

// (中略)

// RootResolver を CanonicalPathResolver でラップして実行する
$rootResolver = new CanonicalPathResolver(new RootResolver());
$target       = $rootResolver->resolve($context);
```

## Tips: シンプルなリダイレクト (RedirectController)

Controller の実装で特定の URL にリダイレクトさせる処理は頻出ですが、
HTTP レスポンスに対する追加処理 (たとえば Cookie の送信やセッションデータの更新など)
を伴わないシンプルなリダイレクトで良いのであれば、Warp の提供している
`Warp\Standard\RedirectController` にそのまま処理を渡して、結果を `return` するという方法が便利です。

```php
namespace App\Controller;

use Woof\Http\Request;
use Woof\Web\Controller;
use Woof\Web\Operator;
use Woof\Web\WebEnvironment;
use Warp\Standard\RedirectController;

class SamplePageController implements Controller
{
    public function handle(Request $req, WebEnvironment $env)
    {
        // 特定の条件を満たした場合は新しいページ (/new-page/) へ 302 リダイレクトする
        if (some_redirect_condition()) {
            return (new RedirectController("/new-page/"))->handle($req, $env);
        }

        // 以後、通常時の処理
        $operator = new Operator($req, $env);
        // (中略)
        return $operator->build();
    }
}
```

## インストール

Composer からインストールできます。
例えば下記のコマンドを実行することで vendor ディレクトリ内に追加されて、使えるようになります。

```bash
composer require apparel-php/warp
```

*※ 依存ライブラリとして Woof も自動的にインストールされます。*

動作要件: PHP 7.0 以上
