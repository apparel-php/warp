<?php

namespace Warp\Pattern;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Warp\Pattern\PlaceholderToken
 */
class PlaceholderTokenTest extends TestCase
{
    /**
     * コンストラクタで渡された名前が正しく保持され、getName() で取得できることを確認します。
     *
     * @covers ::__construct
     * @covers ::getName
     */
    public function testGetName(): void
    {
        $obj = new PlaceholderToken("id");
        $this->assertSame("id", $obj->getName());
    }

    /**
     * @return array testMatchSuccess() のためのテストデータ
     */
    public function provideMatchSuccessCases(): array
    {
        return [
            ["id", null, "123", 3, ["id" => "123"]],
            ["cat", new LiteralToken("/news/"), "sports/news/123", 6, ["cat" => "sports"]],
            ["year", new LiteralToken("-"), "2023-10-25/", 4, ["year" => "2023"]],
        ];
    }

    /**
     * プレースホルダが正しくパスから値を抽出し、成功状態の TokenMatch を返すことを確認します。
     *
     * @param string $name プレースホルダ名
     * @param Token|null $nextToken 次のトークン
     * @param string $path 検証するパス
     * @param int $expectedOffset 期待されるオフセット
     * @param array $expectedParams 期待されるパラメータ配列
     * @dataProvider provideMatchSuccessCases
     * @covers ::match
     */
    public function testMatchSuccess(string $name, Token $nextToken = null, string $path = "", int $expectedOffset = 0, array $expectedParams = []): void
    {
        $obj   = new PlaceholderToken($name);
        $match = $obj->match($path, $nextToken);
        $this->assertTrue($match->isMatched());
        $this->assertSame($expectedOffset, $match->getOffset());
        $this->assertSame($expectedParams, $match->getParameters());
    }

    /**
     * 抽出対象の値の中に次のリテラルと同じ文字列が含まれていると、意図しない途中の位置で抽出される
     * (ただし仕様として許容する) ことを確認するテストです。
     *
     * 例: /post-{slug}-{id}/ というパターンに対し、/post-hello-world-123/ が渡された場合
     *
     * @covers ::match
     */
    public function testMatchWithLiteralNextTokenShortestMatchLimit(): void
    {
        $obj       = new PlaceholderToken("slug");
        $nextToken = new LiteralToken("-");

        // 本来は "hello-world" を slug として抽出してほしいが、
        // 次のリテラル "-" が "hello" の直後にあるため、そこで抽出が完了してしまう
        $match = $obj->match("hello-world-123/", $nextToken);
        $this->assertTrue($match->isMatched());
        $this->assertSame(5, $match->getOffset());
        $this->assertSame(["slug" => "hello"], $match->getParameters());
    }

    /**
     * 次のトークンが存在しない場合の異常系を確認します。
     * 抽出対象の文字列が空であるか、またはスラッシュが含まれる場合はマッチ失敗となります。
     *
     * @covers ::match
     */
    public function testMatchWithNullNextTokenFailed(): void
    {
        $obj    = new PlaceholderToken("id");
        $match1 = $obj->match("123/edit");
        $this->assertFalse($match1->isMatched());
        $match2 = $obj->match("");
        $this->assertFalse($match2->isMatched());
    }

    /**
     * 次のトークンが LiteralToken である場合の異常系を確認します。
     *
     * @covers ::match
     */
    public function testMatchWithLiteralNextTokenFailed(): void
    {
        $obj       = new PlaceholderToken("cat");
        $nextToken = new LiteralToken("/news/");

        // 次のリテラルが見つからない
        $match1 = $obj->match("sports/blogs/", $nextToken);
        $this->assertFalse($match1->isMatched());

        // 次のリテラルがすぐに出現する (プレースホルダ部分が空になる)
        $match2 = $obj->match("/news/123", $nextToken);
        $this->assertFalse($match2->isMatched());

        // プレースホルダ抽出部分にスラッシュが含まれる
        $match3 = $obj->match("sports/local/news/", $nextToken);
        $this->assertFalse($match3->isMatched());
    }

    /**
     * 次のトークンが PlaceholderToken である場合 (プレースホルダの連続) はサポートされず、
     * 常にマッチ失敗となることを確認します。
     *
     * @covers ::match
     */
    public function testMatchWithPlaceholderNextTokenFailed(): void
    {
        $obj       = new PlaceholderToken("year");
        $nextToken = new PlaceholderToken("month");
        $match     = $obj->match("2023-10", $nextToken);
        $this->assertFalse($match->isMatched());
    }
}
