<?php

namespace Warp\Standard;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Warp\ControllerMapper;
use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;
use Woof\Config;
use Woof\Http\Request;
use Woof\Http\Response;
use Woof\NullResources;
use Woof\System\VariablesBuilder;
use Woof\Util\ArrayProperties;
use Woof\Web\Controller;
use Woof\Web\WebEnvironment;
use Woof\Web\WebEnvironmentBuilder;

/**
 * @coversDefaultClass Warp\Standard\MappingResolver
 */
class MappingResolverTest extends TestCase
{
    /**
     * テスト用の RoutingContext オブジェクトを構築するヘルパーメソッドです。
     *
     * @return RoutingContext 構築された RoutingContext オブジェクト
     */
    private function createRoutingContext(): RoutingContext
    {
        $server    = [
            "REQUEST_URI"    => "/",
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST"      => "example.com",
        ];
        $variables = (new VariablesBuilder())
            ->setServer($server)
            ->build();
        $config    = new Config(new ArrayProperties([
            "app" => [
                "root-path"     => "/",
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
     * コンストラクタに ControllerMapper でもその配列でもない不正な値が渡された場合に、
     * InvalidArgumentException がスローされることを確認します。
     *
     * @covers ::__construct
     * @covers ::normalizeMappers
     */
    public function testConstructThrowsExceptionWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Mappers must be a ControllerMapper or an array of ControllerMappers.");

        $inner = new DummyResolver(Target::empty());
        new MappingResolver($inner, "invalid_string");
    }

    /**
     * コンストラクタに渡された配列の中に ControllerMapper を実装していない値が含まれていた場合に、
     * InvalidArgumentException がスローされることを確認します。
     *
     * @covers ::__construct
     * @covers ::normalizeMappers
     */
    public function testConstructThrowsExceptionWithInvalidArrayElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("All mappers must implement ControllerMapper.");

        $inner   = new DummyResolver(Target::empty());
        $mapper  = new DummyMapper();
        new MappingResolver($inner, [$mapper, new stdClass()]);
    }

    /**
     * 単一の Mapper を渡した場合に配列として正規化され、
     * resolve() 実行時に正しく適用されることを確認します。
     *
     * @covers ::__construct
     * @covers ::normalizeMappers
     * @covers ::resolve
     */
    public function testResolveAppliesSingleMapper(): void
    {
        $controller1 = new DummyController();
        $controller2 = new DummyController();
        $inner       = new DummyResolver(Target::found($controller1));

        $mapper = new class($controller2) implements ControllerMapper {
            private $next;
            public $callCount = 0;
            public function __construct(Controller $next)
            {
                $this->next = $next;
            }
            public function map(Controller $controller): Controller
            {
                $this->callCount++;
                return $this->next;
            }
        };

        $obj    = new MappingResolver($inner, $mapper);
        $result = $obj->resolve($this->createRoutingContext());

        $this->assertTrue($result->isFound());
        $this->assertSame($controller2, $result->getController());
        $this->assertSame(1, $mapper->callCount);
    }

    /**
     * 複数の Mapper を配列で渡した場合に、配列の先頭から順番に
     * 連鎖的に map() が適用されること (パイプラインの動作) を確認します。
     *
     * @covers ::__construct
     * @covers ::normalizeMappers
     * @covers ::resolve
     */
    public function testResolveAppliesMappersInOrder(): void
    {
        $controller1 = new DummyController();
        $controller2 = new DummyController();
        $controller3 = new DummyController();
        $inner       = new DummyResolver(Target::found($controller1));
        $callOrder   = [];

        $mapper1 = new class($controller2, $callOrder, "mapper1") implements ControllerMapper {
            private $next;
            private $callOrder;
            private $name;
            public function __construct($next, &$callOrder, $name)
            {
                $this->next      = $next;
                $this->callOrder = &$callOrder;
                $this->name      = $name;
            }
            public function map(Controller $controller): Controller
            {
                $this->callOrder[] = $this->name;
                return $this->next;
            }
        };

        $mapper2 = new class($controller3, $callOrder, "mapper2") implements ControllerMapper {
            private $next;
            private $callOrder;
            private $name;
            public function __construct($next, &$callOrder, $name)
            {
                $this->next      = $next;
                $this->callOrder = &$callOrder;
                $this->name      = $name;
            }
            public function map(Controller $controller): Controller
            {
                $this->callOrder[] = $this->name;
                return $this->next;
            }
        };

        $obj    = new MappingResolver($inner, [$mapper1, $mapper2]);
        $result = $obj->resolve($this->createRoutingContext());

        $this->assertTrue($result->isFound());
        $this->assertSame($controller3, $result->getController());
        $this->assertSame(["mapper1", "mapper2"], $callOrder);
    }

    /**
     * 下位の Resolver が空 (empty) の Target を返した場合、
     * Mapper は一切実行されず、空のまま返却されることを確認します。
     *
     * @covers ::resolve
     */
    public function testResolveWhenTargetIsEmpty(): void
    {
        $inner = new DummyResolver(Target::empty());

        $mapper = new class implements ControllerMapper {
            public function map(Controller $controller): Controller
            {
                throw new LogicException("Mapper should not be called when target is empty.");
            }
        };

        $obj    = new MappingResolver($inner, [$mapper]);
        $result = $obj->resolve($this->createRoutingContext());

        $this->assertFalse($result->isFound());
    }
}

/**
 * テスト用のダミーリゾルバーです。
 */
class DummyResolver implements Resolver
{
    private $target;

    public function __construct(Target $target)
    {
        $this->target = $target;
    }

    public function resolve(RoutingContext $context): Target
    {
        return $this->target;
    }
}

/**
 * テスト用のダミーマッパーです。
 */
class DummyMapper implements ControllerMapper
{
    public function map(Controller $controller): Controller
    {
        return $controller;
    }
}

/**
 * テスト用のダミーコントローラーです。
 */
class DummyController implements Controller
{
    public function handle(Request $request, WebEnvironment $env): Response
    {
        throw new LogicException("Not implemented.");
    }
}
