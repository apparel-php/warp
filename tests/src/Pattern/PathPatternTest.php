<?php

namespace Warp\Pattern;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Warp\Pattern\PathPattern
 */
class PathPatternTest extends TestCase
{
    /**
     * @return array testParse() のためのテストデータ
     */
    public function provideParseCases(): array
    {
        $admin = [new LiteralToken("/admin/")];
        $edit  = [new LiteralToken("/edit/"), new PlaceholderToken("id"), new LiteralToken("/")];
        $arch  = [new LiteralToken("/archives/"), new PlaceholderToken("year"), new LiteralToken("-"), new PlaceholderToken("month"), new LiteralToken("-"), new PlaceholderToken("date"), new LiteralToken("/")];

        return [
            ["/admin/", $admin],
            ["/edit/{id}/", $edit],
            ["/archives/{year}-{month}-{date}/", $arch],
            ["", []],
        ];
    }

    /**
     * テンプレート文字列からトークン配列が正しく構築されることを確認します。
     *
     * @param string $template テンプレート文字列
     * @param Token[] $expectedTokens 期待されるトークンの配列
     * @dataProvider provideParseCases
     * @covers ::__construct
     * @covers ::parse
     * @covers ::getTokens
     * @covers ::<private>
     */
    public function testParse(string $template, array $expectedTokens): void
    {
        $obj = PathPattern::parse($template);
        $this->assertEquals($expectedTokens, $obj->getTokens());
    }

    /**
     * @return array testMatch() のためのテストデータ
     */
    public function provideMatchCases(): array
    {
        return [
            ["/admin/", "/admin/", []],
            ["/admin/", "/user/", null],
            ["/admin/", "/admin/extra", null],
            ["/edit/{id}/", "/edit/123/", ["id" => "123"]],
            ["/category/{cat}/news/{id}", "/category/sports/news/456", ["cat" => "sports", "id" => "456"]],
            ["/archives/{year}-{month}-{date}/", "/archives/2023-10-25/", ["year" => "2023", "month" => "10", "date" => "25"]],
            ["/edit/{id}/", "/remove/123/", null],
            ["/edit/{id}/", "/edit/123", null],
            ["/edit/{id}/", "/edit/123//", null],
            ["/edit/{id}/", "/edit//", null],
        ];
    }

    /**
     * パターンに部分パスを適用し、マッチ結果とパラメータが正しく取得できること、
     * またはマッチしない場合に失敗状態の RouteMatch が返ることを確認します。
     *
     * @param string $template テンプレート文字列
     * @param string $path 検証対象の部分パス
     * @param array|null $expectedParams 期待されるパラメータの連想配列 (失敗時は null)
     * @dataProvider provideMatchCases
     * @covers ::match
     */
    public function testMatch(string $template, string $path, $expectedParams = null): void
    {
        $obj   = PathPattern::parse($template);
        $match = $obj->match($path);

        if ($expectedParams !== null) {
            $this->assertTrue($match->isMatched());
            foreach ($expectedParams as $key => $val) {
                $this->assertSame($val, $match->get($key));
            }
        } else {
            $this->assertFalse($match->isMatched());
        }
    }
}
