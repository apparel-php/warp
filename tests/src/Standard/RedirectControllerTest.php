<?php

namespace Warp\Standard;

use PHPUnit\Framework\TestCase;
use Woof\Config;
use Woof\Http\Status;
use Woof\NullResources;
use Woof\System\VariablesBuilder;
use Woof\Util\ArrayProperties;
use Woof\Web\WebEnvironment;
use Woof\Web\WebEnvironmentBuilder;

/**
 * @coversDefaultClass Warp\Standard\RedirectController
 */
class RedirectControllerTest extends TestCase
{
    /**
     * テスト用の WebEnvironment オブジェクトを構築するヘルパーメソッドです。
     *
     * @param string $path リクエストパス
     * @param array $query GET パラメータの配列
     * @param string $rootPath アプリケーションのルートパス (app.root-path)
     * @return WebEnvironment 構築された WebEnvironment オブジェクト
     */
    private function createEnvironment(string $path, array $query = [], string $rootPath = "/"): WebEnvironment
    {
        $server = [
            "REQUEST_URI"    => $path,
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST"      => "example.com",
            "HTTPS"          => "on",
        ];

        $variables = (new VariablesBuilder())
            ->setServer($server)
            ->setGet($query)
            ->build();

        $config = new Config(new ArrayProperties([
            "app" => [
                "root-path"     => $rootPath,
                "arg-separator" => "&"
            ]
        ]));

        return (new WebEnvironmentBuilder())
            ->setConfig($config)
            ->setResources(NullResources::getInstance())
            ->setVariables($variables)
            ->build();
    }

    /**
     * testHandle() のためのデータプロバイダです。
     *
     * @return array app.root-path, リダイレクト先設定値, クエリ引継ぎフラグ, リクエストクエリ, 期待される完全 URL の組み合わせ
     */
    public function provideTestHandle(): array
    {
        return [
            // クエリを引き継がないケース
            ["/my-app", "/dashboard", false, ["page" => "2"], "https://example.com/my-app/dashboard"],
            ["/admin-sys", "/login", false, [], "https://example.com/admin-sys/login"],
            // クエリを引き継ぐケース
            ["/shop", "/search/results", true, ["keyword" => "warp", "sort" => "desc"], "https://example.com/shop/search/results?keyword=warp&sort=desc"],
            // root-path が設定されていない (空文字列) ケース
            ["", "/about", true, [], "https://example.com/about"],
        ];
    }

    /**
     * handle() が指定されたアプリケーションパスを元に app.root-path を加味した完全 URL を構築し、
     * 設定に応じてクエリを維持または破棄してリダイレクトする Response を返すことを確認します。
     *
     * @dataProvider provideTestHandle
     * @covers ::__construct
     * @covers ::handle
     */
    public function testHandle(string $rootPath, string $appPath, bool $preserveQuery, array $requestQuery, string $expectedLocation)
    {
        $env      = $this->createEnvironment("/some/old/path", $requestQuery, $rootPath);
        $request  = $env->getClientRequest();
        $obj      = new RedirectController($appPath, $preserveQuery);
        $response = $obj->handle($request, $env);
        $this->assertEquals(Status::get302(), $response->getStatus());
        $this->assertSame($expectedLocation, $response->getHeader("Location")->getValue());
    }
}
