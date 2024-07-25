<?php

namespace Warp;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Warp\RouteMatch
 */
class RouteMatchTest extends TestCase
{
    /**
     * matched() で生成された成功状態のインスタンスについて、
     * 想定通りのパラメータを保持していることと、存在しないキーに対してデフォルト値を返すことを確認します。
     *
     * @covers ::__construct
     * @covers ::matched
     * @covers ::isMatched
     * @covers ::get
     */
    public function testMatched(): void
    {
        $obj = RouteMatch::matched(["id" => "123", "type" => "admin"]);
        $this->assertTrue($obj->isMatched());
        $this->assertSame("123", $obj->get("id"));
        $this->assertSame("admin", $obj->get("type"));
        $this->assertSame("", $obj->get("not_exists"));
        $this->assertSame("fallback", $obj->get("not_exists", "fallback"));
    }

    /**
     * failed() で取得される失敗状態のインスタンスの各種状態を確認します。
     * 下記に挙げる条件を満たしていれば OK とします。
     *
     * - 常に同じインスタンスを返すこと
     * - マッチ結果が false になること
     * - パラメータ取得時に常にデフォルト値を返すこと
     *
     * @covers ::__construct
     * @covers ::failed
     * @covers ::isMatched
     * @covers ::get
     */
    public function testFailed(): void
    {
        $obj1 = RouteMatch::failed();
        $obj2 = RouteMatch::failed();
        $this->assertFalse($obj1->isMatched());
        $this->assertSame($obj1, $obj2);
        $this->assertSame("", $obj1->get("id"));
        $this->assertSame("default_value", $obj1->get("id", "default_value"));
    }
}
