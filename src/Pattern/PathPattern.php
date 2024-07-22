<?php

namespace Warp\Pattern;

use Warp\RouteMatch;

/**
 * URL ルーティングのパターンを解析し、マッチングを統括するクラスです。
 */
class PathPattern
{
    /**
     * @var Token[]
     */
    private $tokens;

    /**
     * トークン列を指定して PathPattern オブジェクトを構築します。
     *
     * @param Token[] $tokens トークンの配列
     */
    private function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * テンプレート文字列を解析し、PathPattern オブジェクトを生成します。
     *
     * @param string $template テンプレート文字列
     * @return self 構築された PathPattern オブジェクト
     */
    public static function parse(string $template): self
    {
        $tokens = self::parseTemplate($template);
        return new self($tokens);
    }

    /**
     * テンプレート文字列を再帰的にパースし、Token の配列を生成します。
     *
     * @param string $template テンプレート文字列
     * @return Token[] トークンの配列
     */
    private static function parseTemplate(string $template): array
    {
        if ($template === "") {
            return [];
        }

        // プレースホルダを特定する
        if (preg_match('/\{([a-zA-Z0-9_]+)\}/', $template, $matches, PREG_OFFSET_CAPTURE)) {
            $name   = $matches[1][0];
            $start  = $matches[0][1];
            $length = strlen($matches[0][0]);

            $left  = substr($template, 0, $start);
            $right = substr($template, $start + $length);

            $token = [new PlaceholderToken($name)];

            $parsedLeft  = self::parseTemplate($left);
            $parsedRight = self::parseTemplate($right);

            return array_merge($parsedLeft, $token, $parsedRight);
        }

        // プレースホルダが含まれていない場合はリテラルとして扱う
        return [new LiteralToken($template)];
    }

    /**
     * 指定された部分パスがパターンにマッチするか検証します。
     *
     * @param string $path 検証対象の部分パス
     * @return RouteMatch マッチ結果を保持するオブジェクト
     */
    public function match(string $path): RouteMatch
    {
        $parameters  = [];
        $currentPath = $path;
        $tokensCount = count($this->tokens);

        foreach ($this->tokens as $index => $token) {
            $nextToken   = $index + 1 < $tokensCount ? $this->tokens[$index + 1] : null;
            $matchResult = $token->match($currentPath, $nextToken);
            if (!$matchResult->isMatched()) {
                return RouteMatch::failed();
            }

            $parameters  = array_merge($parameters, $matchResult->getParameters());
            $currentPath = substr($currentPath, $matchResult->getOffset());
        }

        // すべてのトークンを処理した上で、パス文字列が余っている場合は失敗とします
        return ($currentPath === "") ? RouteMatch::matched($parameters) : RouteMatch::failed();
    }

    /**
     * 保持しているトークンの配列を取得します。
     *
     * @return Token[] テンプレートの解析結果のトークンの配列
     * @ignore
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }
}
