<?php

namespace Warp;

use LogicException;
use PHPUnit\Framework\TestCase;
use Woof\Http\Request;
use Woof\Web\Controller;
use Woof\Web\WebEnvironment;

/**
 * @coversDefaultClass Warp\Target
 */
class TargetTest extends TestCase
{
    /**
     * 宛先が見つかった場合の Target インスタンスの振る舞いを確認します。
     *
     * @covers ::__construct
     * @covers ::found
     * @covers ::isFound
     * @covers ::getController
     */
    public function testFound()
    {
        $controller = new DummyController();
        $target     = Target::found($controller);

        $this->assertTrue($target->isFound());
        $this->assertSame($controller, $target->getController());
    }

    /**
     * 宛先が見つからなかった場合の Target インスタンスの振る舞いと、
     * シングルトンとして同一のインスタンスが返されることを確認します。
     *
     * @covers ::__construct
     * @covers ::empty
     * @covers ::isFound
     */
    public function testEmpty()
    {
        $obj1 = Target::empty();
        $obj2 = Target::empty();
        $this->assertSame($obj1, $obj2);
        $this->assertFalse($obj1->isFound());
    }

    /**
     * 宛先が見つからなかった Target インスタンスから Controller を取得しようとした際に、
     * 期待される例外がスローされることを確認します。
     *
     * @covers ::getController
     */
    public function testGetControllerThrowsExceptionWhenEmpty()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Cannot get controller from an empty target.");
        $obj = Target::empty();
        $obj->getController();
    }

    /**
     * 宛先が見つかっている状態の Target に対して map() メソッドを実行した際、
     * 指定した Mapper を経由して新しい Target オブジェクトが生成されることを確認します。
     *
     * @covers ::map
     */
    public function testMapWhenFound()
    {
        $ctrl1  = new DummyController();
        $ctrl2  = new DummyController();
        $mapper = new class($ctrl2, $ctrl1) implements ControllerMapper {
            public $callCount = 0;
            private $returnController;
            private $expectedController;
            public function __construct(Controller $returnController, Controller $expectedController)
            {
                $this->returnController   = $returnController;
                $this->expectedController = $expectedController;
            }
            public function map(Controller $controller): Controller
            {
                $this->callCount++;
                return $this->returnController;
            }
        };

        $obj1 = Target::found($ctrl1);
        $obj2 = $obj1->map($mapper);
        $this->assertSame(1, $mapper->callCount);
        $this->assertNotSame($obj1, $obj2);
        $this->assertTrue($obj2->isFound());
        $this->assertSame($ctrl2, $obj2->getController());
    }

    /**
     * 宛先が見つかっていない状態の Target に対して map() メソッドを実行した際、
     * Mapper が実行されず、自身と同じインスタンスが返されることを確認します。
     *
     * @covers ::map
     */
    public function testMapWhenEmpty()
    {
        // 実行されたら例外を投げる無名クラス (Mapper) を定義
        $mapper = new class implements ControllerMapper {
            public function map(Controller $controller): Controller
            {
                throw new LogicException("Mapper should not be called when target is empty.");
            }
        };

        $obj1 = Target::empty();
        $obj2 = $obj1->map($mapper);
        $this->assertSame($obj1, $obj2);
    }

    /**
     * 宛先が見つかっている状態の Target に対して unwrapOr() メソッドを実行した際、
     * 自身が保持している Controller が返されることを確認します。
     *
     * @covers ::unwrapOr
     */
    public function testUnwrapOrWhenFound()
    {
        $ctrl1 = new DummyController();
        $ctrl2 = new DummyController();
        $obj   = Target::found($ctrl1);
        $this->assertSame($ctrl1, $obj->unwrapOr($ctrl2));
    }

    /**
     * 宛先が見つかっていない状態の Target に対して unwrapOr() メソッドを実行した際、
     * 引数で指定された代替の Controller が返されることを確認します。
     *
     * @covers ::unwrapOr
     */
    public function testUnwrapOrWhenEmpty()
    {
        $ctrl = new DummyController();
        $obj  = Target::empty();
        $this->assertSame($ctrl, $obj->unwrapOr($ctrl));
    }

    /**
     * 宛先が見つかっている状態の Target に対して unwrapOr() にクロージャを渡した際、
     * クロージャは実行されず (遅延評価)、自身が保持している Controller が返されることを確認します。
     *
     * @covers ::unwrapOr
     */
    public function testUnwrapOrWhenFoundWithClosure()
    {
        $ctrl1 = new DummyController();
        $obj   = Target::found($ctrl1);

        $fallback = function () {
            throw new LogicException("Closure should not be evaluated when target is found.");
        };

        $this->assertSame($ctrl1, $obj->unwrapOr($fallback));
    }

    /**
     * 宛先が見つかっていない状態の Target に対して unwrapOr() にクロージャを渡した際、
     * クロージャが実行され、その結果の Controller が返されることを確認します。
     *
     * @covers ::unwrapOr
     */
    public function testUnwrapOrWhenEmptyWithClosure()
    {
        $ctrl = new DummyController();
        $obj  = Target::empty();

        $fallback = function () use ($ctrl) {
            return $ctrl;
        };

        $this->assertSame($ctrl, $obj->unwrapOr($fallback));
    }

    /**
     * unwrapOr() に Controller 以外の不正な値 (または不正な値を返すクロージャ)
     * を渡した際に、LogicException がスローされることを確認します。
     *
     * @covers ::unwrapOr
     */
    public function testUnwrapOrThrowsExceptionWithInvalidFallback()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The fallback must be an instance of Controller or a callable returning a Controller.");

        $obj = Target::empty();
        $obj->unwrapOr(function () {
            return "Invalid Controller";
        });
    }
}

/**
 * テスト用のダミーコントローラーです。
 * Target クラスのテストにおいては handle() メソッドが実行されることはないため、実装は空としています。
 */
class DummyController implements Controller
{
    /**
     * @param Request $request
     * @param WebEnvironment $env
     */
    public function handle(Request $request, WebEnvironment $env)
    {
        // do nothing
    }
}
