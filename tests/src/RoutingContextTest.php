<?php

namespace Warp;

use PHPUnit\Framework\TestCase;
use Woof\Config;
use Woof\NullResources;
use Woof\System\VariablesBuilder;
use Woof\Util\ArrayProperties;
use Woof\Web\WebEnvironmentBuilder;

/**
 * @coversDefaultClass Warp\RoutingContext
 */
class RoutingContextTest extends TestCase
{
    /**
     * テスト用の RoutingContext オブジェクトを構築するヘルパーメソッドです。
     * WebEnvironmentBuilder を利用し、正規のプロセスで Request と Environment を生成します。
     *
     * @param string $path リクエストパス
     * @param string $rootPath アプリケーションのルートパス (app.root-path)
     * @param string $method HTTP メソッド
     * @param array $query GET パラメータの配列
     * @param array $post POST パラメータの配列
     * @return RoutingContext 構築された RoutingContext オブジェクト
     */
    private function createRoutingContext(string $path, string $rootPath = "/", string $method = "get", array $query = [], array $post = []): RoutingContext
    {
        $server = [
            "REQUEST_URI"    => $path,
            "REQUEST_METHOD" => strtoupper($method),
            "HTTP_HOST"      => "example.com",
        ];

        $variables = (new VariablesBuilder())
            ->setServer($server)
            ->setGet($query)
            ->setPost($post)
            ->build();

        $config = new Config(new ArrayProperties([
            "app" => [
                "root-path"     => $rootPath,
                "arg-separator" => "&",
            ]
        ]));

        $env = (new WebEnvironmentBuilder())
            ->setConfig($config)
            ->setResources(NullResources::getInstance())
            ->setVariables($variables)
            ->build();

        return RoutingContext::create($env->getClientRequest(), $env);
    }

    /**
     * testCreateAndNormalization() のためのテストデータを提供します。
     *
     * @return array 入力パス文字列, app.root-path の設定値, 期待されるセグメント配列, 期待される appPath のリスト
     */
    public function provideTestCreateAndNormalization(): array
    {
        return [
            // app.root-path が未定義 ("/" または "") のケース
            ["/admin/users/add", "/", ["admin", "users", "add"], "/admin/users/add"],
            ["admin/users/add", "", ["admin", "users", "add"], "/admin/users/add"],
            ["/admin/users/add/", "/", ["admin", "users", "add", ""], "/admin/users/add/"],
            ["///admin//users///add///", "", ["admin", "users", "add", ""], "/admin/users/add/"],
            ["/", "/", [""], "/"],
            ["", "", [""], "/"],
            ["///", "/", [""], "/"],
            ["/login", "", ["login"], "/login"],
            ["/login/", "/", ["login", ""], "/login/"],

            // app.root-path が指定されているケース
            ["/my-app/admin/users", "/my-app", ["admin", "users"], "/admin/users"],
            ["/my-app/admin/users/", "/my-app/", ["admin", "users", ""], "/admin/users/"],
            ["/my-app/", "/my-app", [""], "/"],
            ["/my-app", "/my-app", [""], "/"],
            ["/my-app/admin", "my-app", ["admin"], "/admin"],
            ["/my-app//admin//", "//my-app//", ["admin", ""], "/admin/"],
        ];
    }

    /**
     * create() メソッドがリクエストパスを正しく解析し、
     * 連続するセパレーターや先頭・末尾のセパレーターを排除して初期化することを確認します。
     *
     * @dataProvider provideTestCreateAndNormalization
     * @covers ::__construct
     * @covers ::create
     * @covers ::getSegments
     * @covers ::getAppPath
     * @covers ::hasNextSegment
     * @covers ::peek
     * @covers ::<private>
     */
    public function testCreateAndNormalization(string $path, string $rootPath, array $expectedSegments, string $expectedAppPath)
    {
        $obj = $this->createRoutingContext($path, $rootPath);
        $this->assertSame($expectedSegments, $obj->getSegments());
        $this->assertSame($expectedAppPath, $obj->getAppPath());

        if (count($expectedSegments) > 0) {
            $this->assertTrue($obj->hasNextSegment());
            $this->assertSame($expectedSegments[0], $obj->peek());
        } else {
            $this->assertFalse($obj->hasNextSegment());
            $this->assertSame("", $obj->peek());
        }
    }

    /**
     * リクエストパスが app.root-path から始まらない場合に、
     * RootPathMismatchException がスローされることを確認します。
     *
     * @covers ::create
     * @covers ::<private>
     */
    public function testCreateThrowsExceptionWhenBasePathMismatched()
    {
        $this->expectException(RootPathMismatchException::class);

        // my-app というベースパスが設定されているにもかかわらず、異なるパスでアクセスされた場合
        $this->createRoutingContext("/admin/users/add", "/my-app");
    }

    /**
     * shift() メソッドが現在のコンテキストを変更せず、
     * パスが 1 つ進んだ新しいインスタンスを生成して返すことを確認します。
     *
     * @covers ::shift
     * @covers ::hasNextSegment
     * @covers ::peek
     * @covers ::getAppPath
     */
    public function testShift()
    {
        $obj1 = $this->createRoutingContext("/articles/edit/123");

        // 1回目の shift (articles を消費)
        $obj2 = $obj1->shift();
        $this->assertNotSame($obj1, $obj2);
        $this->assertSame("articles", $obj1->peek());
        $this->assertSame("edit", $obj2->peek());
        $this->assertTrue($obj2->hasNextSegment());
        $this->assertSame("/articles/edit/123", $obj2->getAppPath());

        // 2回目の shift (edit を消費)
        $obj3 = $obj2->shift();
        $this->assertSame("123", $obj3->peek());
        $this->assertTrue($obj3->hasNextSegment());
        $this->assertSame("/articles/edit/123", $obj3->getAppPath());

        // 3回目の shift (123 を消費し、末尾に到達)
        $obj4 = $obj3->shift();
        $this->assertSame("", $obj4->peek());
        $this->assertFalse($obj4->hasNextSegment());
        $this->assertSame("/articles/edit/123", $obj4->getAppPath());
    }

    /**
     * 未処理のセグメントが存在しない状態で shift() を実行した場合に、
     * NoMoreSegmentException がスローされることを確認します。
     *
     * @covers ::shift
     */
    public function testShiftThrowsExceptionWhenEmpty()
    {
        $obj1 = $this->createRoutingContext("/");
        $obj2 = $obj1->shift();

        $this->expectException(NoMoreSegmentException::class);
        $this->expectExceptionMessage("No more segments to shift");
        $obj2->shift();
    }

    /**
     * Request および WebEnvironment に対する委譲 (デリゲート) メソッドや、
     * 依存オブジェクトを取得するメソッドが正しく機能することを確認します。
     *
     * @covers ::getRequest
     * @covers ::getEnvironment
     * @covers ::getMethod
     * @covers ::getQuery
     * @covers ::getPost
     */
    public function testDelegationMethods()
    {
        $obj     = $this->createRoutingContext("/test", "/", "post", ["sort" => "desc", "page" => "2"], ["title" => "hello"]);
        $request = $obj->getRequest();
        $env     = $obj->getEnvironment();
        $this->assertSame($request, $env->getClientRequest());
        $this->assertSame("post", $obj->getMethod());
        $this->assertSame("desc", $obj->getQuery("sort"));
        $this->assertSame("default_value", $obj->getQuery("not_found", "default_value"));
        $this->assertSame("hello", $obj->getPost("title"));
        $this->assertSame("default_value", $obj->getPost("not_found", "default_value"));
    }

    /**
     * testGetPartialPath() のためのテストデータを提供します。
     *
     * @return array 入力パス文字列, 期待される部分パスのリスト
     */
    public function provideTestGetPartialPath(): array
    {
        return [
            ["/subdir/subpage/", "/subdir/subpage/"],
            ["/subdir/favicon.ico", "/subdir/favicon.ico"],
            ["/", "/"],
        ];
    }

    /**
     * getPartialPath() が未処理のセグメントから正しく部分パスを復元できることを確認します。
     *
     * @dataProvider provideTestGetPartialPath
     * @covers ::getPartialPath
     */
    public function testGetPartialPath(string $path, string $expectedPath)
    {
        $obj = $this->createRoutingContext($path);
        $this->assertSame($expectedPath, $obj->getPartialPath());
    }

    /**
     * すべてのセグメントを消費しきった状態で getPartialPath() を実行した場合に
     * 空文字列が返ることを確認します。
     *
     * @covers ::getPartialPath
     */
    public function testGetPartialPathWhenEmpty()
    {
        $obj = $this->createRoutingContext("/")->shift();
        $this->assertSame("", $obj->getPartialPath());
    }

    /**
     * testMatchPath() のためのテストデータを提供します。
     *
     * @return array 入力パス文字列, テンプレート文字列, マッチ結果の期待値(連想配列またはnull)のリスト
     */
    public function provideTestMatchPath(): array
    {
        return [
            // マッチ成功 (プレースホルダなし)
            ["/about/", "/about/", []],

            // マッチ成功 (プレースホルダあり)
            ["/edit/123/", "/edit/{id}/", ["id" => "123"]],
            ["/category/sports/news/456", "/category/{cat}/news/{id}", ["cat" => "sports", "id" => "456"]],

            // マッチ失敗 (パスが異なる)
            ["/edit/123/", "/remove/{id}/", null],

            // マッチ失敗 (スラッシュの有無が異なる)
            ["/edit/123", "/edit/{id}/", null], // 末尾スラッシュ不足
            ["/edit/123/", "/edit/{id}", null], // 末尾スラッシュ過剰

            // マッチ失敗 (プレースホルダに該当する文字列がない)
            ["/edit//", "/edit/{id}/", null], // 連続スラッシュになっているケース
        ];
    }

    /**
     * matchPath() がプレースホルダを展開して正規表現マッチを行い、
     * マッチした場合はパラメータを抽出し、マッチしなかった場合は失敗状態を返すことを確認します。
     *
     * @dataProvider provideTestMatchPath
     * @covers ::matchPath
     */
    public function testMatchPath(string $path, string $template, array $expectedParams = null)
    {
        $obj   = $this->createRoutingContext($path);
        $match = $obj->matchPath($template);

        if ($expectedParams !== null) {
            $this->assertTrue($match->isMatched());
            foreach ($expectedParams as $key => $val) {
                $this->assertSame($val, $match->get($key));
            }
            $this->assertSame("", $match->get("not_exists"));
        } else {
            $this->assertFalse($match->isMatched());
        }
    }
}
