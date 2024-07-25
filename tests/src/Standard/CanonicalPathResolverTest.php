<?php

namespace Warp\Standard;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;
use Woof\Config;
use Woof\Http\Request;
use Woof\NullResources;
use Woof\System\VariablesBuilder;
use Woof\Util\ArrayProperties;
use Woof\Web\Controller;
use Woof\Web\WebEnvironment;
use Woof\Web\WebEnvironmentBuilder;

/**
 * @coversDefaultClass Warp\Standard\CanonicalPathResolver
 */
class CanonicalPathResolverTest extends TestCase
{
    /**
     * テスト用の RoutingContext オブジェクトを構築するヘルパーメソッドです。
     *
     * @param string $path リクエストパス
     * @param string $rootPath アプリケーションのルートパス (app.root-path)
     * @return RoutingContext 構築された RoutingContext オブジェクト
     */
    private function createRoutingContext(string $path, string $rootPath = "/"): RoutingContext
    {
        $server = [
            "REQUEST_URI"    => $path,
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST"      => "example.com",
            "HTTPS"          => "on",
        ];

        $variables = (new VariablesBuilder())
            ->setServer($server)
            ->build();

        $config = new Config(new ArrayProperties([
            "app" => [
                "root-path"     => $rootPath,
                "arg-separator" => "&"
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
     * testRedirectCases() のためのテストデータを提供します。
     *
     * @return array テストデータの配列 [リクエストパス, 期待されるリダイレクト先 (完全URL)]
     */
    public function provideTestRedirectCases(): array
    {
        return [
            // インデックスパターン
            ["/inquiry/index.php", "https://example.com/inquiry/"],
            ["/index.html", "https://example.com/"],

            // 直前のセグメントがインデックスパターン (末尾スラッシュ付き)
            ["/inquiry/index.php/", "https://example.com/inquiry/"],
            ["/index.html/", "https://example.com/"],

            // 直前のセグメントがファイル名パターン (末尾スラッシュ付き)
            ["/inquiry/logo.png/", "https://example.com/inquiry/logo.png"],
            ["/app.js/", "https://example.com/app.js"],

            // 末尾がディレクトリ名である (スラッシュが付いていない)
            ["/inquiry", "https://example.com/inquiry/"],
            ["/inquiry/form", "https://example.com/inquiry/form/"],
        ];
    }

    /**
     * インデックスパターンや不正な末尾スラッシュの欠如などを検知した場合に、
     * 正規化された URL へリダイレクトする Target (RedirectController) に解決されることを確認します。
     *
     * @param string $path リクエストパス
     * @param string $expectedLocation 期待されるリダイレクト先 (完全 URL)
     * @dataProvider provideTestRedirectCases
     * @covers ::__construct
     * @covers ::resolve
     * @covers ::<private>
     */
    public function testRedirectCases(string $path, string $expectedLocation): void
    {
        // リダイレクトされる場合、本来の resolve() は呼ばれてはならない
        $main = new class implements Resolver {
            public function resolve(RoutingContext $context): Target
            {
                throw new RuntimeException("This main resolver should never be called in redirect cases.");
            }
        };

        $context = $this->createRoutingContext($path);
        $obj     = new CanonicalPathResolver($main);
        $target  = $obj->resolve($context);
        $this->assertInstanceOf(Target::class, $target);
        $this->assertTrue($target->isFound());

        $controller = $target->getController();
        $this->assertInstanceOf(RedirectController::class, $controller);

        $response = $controller->handle($context->getRequest(), $context->getEnvironment());
        $this->assertSame($expectedLocation, $response->getHeader("Location")->getValue());
    }

    /**
     * testFallbackCases() のためのテストデータを提供します。
     *
     * @return array テストデータの配列 [リクエストパス]
     */
    public function provideTestFallbackCases(): array
    {
        return [
            ["/"],
            ["/inquiry/"],
            ["/inquiry/logo.png"],
            ["/assets/style.css"],
        ];
    }

    /**
     * 正規化が不要な正しい URL パターンの場合に、
     * 本来の処理を担当する Resolver に解決処理が移譲されることを確認します。
     *
     * @param string $path リクエストパス
     * @dataProvider provideTestFallbackCases
     * @covers ::resolve
     * @covers ::<private>
     */
    public function testFallbackCases(string $path): void
    {
        $controller = new class implements Controller {
            public function handle(Request $request, WebEnvironment $env) {
                return null;
            }
        };

        $context = $this->createRoutingContext($path);
        $target  = Target::found($controller);
        $main    = new class($target) implements Resolver {
            private $target;
            public $callCount = 0;
            public function __construct(Target $target)
            {
                $this->target = $target;
            }
            public function resolve(RoutingContext $context): Target
            {
                $this->callCount++;
                return $this->target;
            }
        };

        $obj    = new CanonicalPathResolver($main);
        $result = $obj->resolve($context);
        $this->assertSame(1, $main->callCount);
        $this->assertSame($target, $result);
    }

    /**
     * (通常は発生し得ないですが) RoutingContext のセグメントが空の場合に、
     * エラーにならず安全に本来の Resolver に処理が移譲されることを確認します。
     *
     * @covers ::resolve
     */
    public function testResolveEmptySegments(): void
    {
        $controller = new class implements Controller {
            public function handle(Request $request, WebEnvironment $env) {
                return null;
            }
        };

        $context = $this->createRoutingContext("/")->shift();
        $target  = Target::found($controller);
        $main    = new class($target) implements Resolver {
            private $target;
            public $callCount = 0;
            public function __construct(Target $target)
            {
                $this->target = $target;
            }
            public function resolve(RoutingContext $context): Target
            {
                $this->callCount++;
                return $this->target;
            }
        };

        $obj    = new CanonicalPathResolver($main);
        $result = $obj->resolve($context);
        $this->assertSame(1, $main->callCount);
        $this->assertSame($target, $result);
    }
}
