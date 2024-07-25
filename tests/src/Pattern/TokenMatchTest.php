<?php

namespace Warp\Pattern;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Warp\Pattern\TokenMatch
 */
class TokenMatchTest extends TestCase
{
    /**
     * matched() で生成された成功状態のインスタンスについて、
     * 想定通りのパラメータとオフセットを保持していることを確認します。
     *
     * @covers ::__construct
     * @covers ::matched
     * @covers ::isMatched
     * @covers ::getOffset
     * @covers ::getParameters
     */
    public function testMatched(): void
    {
        $obj = TokenMatch::matched(5, ["id" => "123"]);
        $this->assertTrue($obj->isMatched());
        $this->assertSame(5, $obj->getOffset());
        $this->assertSame(["id" => "123"], $obj->getParameters());
    }

    /**
     * failed() で取得される失敗状態のインスタンスの各種状態を確認します。
     * 下記に挙げる条件を満たしていれば OK とします。
     *
     * - 常に同じインスタンスを返すこと
     * - マッチ結果が false になること
     * - offset が 0 になること
     * - パラメータが空配列になること
     *
     * @covers ::__construct
     * @covers ::failed
     * @covers ::isMatched
     * @covers ::getOffset
     * @covers ::getParameters
     */
    public function testFailed(): void
    {
        $obj1 = TokenMatch::failed();
        $obj2 = TokenMatch::failed();
        $this->assertFalse($obj1->isMatched());
        $this->assertSame($obj1, $obj2);
        $this->assertSame(0, $obj1->getOffset());
        $this->assertSame([], $obj1->getParameters());
    }
}
