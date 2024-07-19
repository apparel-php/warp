<?php

namespace Warp\Pattern;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Warp\Pattern\LiteralToken
 */
class LiteralTokenTest extends TestCase
{
    /**
     * コンストラクタで渡された文字列が正しく保持され、getText() で取得できることを確認します。
     *
     * @covers ::__construct
     * @covers ::getText
     */
    public function testGetText(): void
    {
        $obj = new LiteralToken("/admin/");
        $this->assertSame("/admin/", $obj->getText());
    }

    /**
     * match() メソッドが、パスの先頭がリテラル文字列と一致した場合に、
     * 成功状態の TokenMatch を返すことを確認します。
     *
     * @covers ::match
     */
    public function testMatchSuccess(): void
    {
        $obj   = new LiteralToken("/edit/");
        $match = $obj->match("/edit/123");
        $this->assertTrue($match->isMatched());
        $this->assertSame(6, $match->getOffset());
        $this->assertSame([], $match->getParameters());
    }

    /**
     * match() メソッドが、パスの先頭がリテラル文字列と一致しなかった場合に、
     * 失敗状態の TokenMatch を返すことを確認します。
     *
     * @covers ::match
     */
    public function testMatchReturnsFailed(): void
    {
        $obj   = new LiteralToken("/edit/");
        $match = $obj->match("/remove/123");
        $this->assertFalse($match->isMatched());
    }
}
