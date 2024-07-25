<?php

namespace Warp\Standard;

use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;

/**
 * URL の正規化 (Canonicalization) を担当する Resolver です。
 *
 * イレギュラーな URL パターンを検知した場合、正規化されたパスへリダイレクトする RedirectController に解決します。
 * 正規化が不要 (正しい URL) であると判断された場合は、本来の処理を担当する Resolver に処理を移譲します。
 *
 * 正規化の判断材料となるパターンは以下の通りです。
 *
 * - インデックスパターン: "index.php" や "index.html" などのインデックスファイル名
 * - ファイル名パターン: 拡張子を持つファイル名 (例: "logo.png", "app.js")
 *
 * 正規化されるパスのパターンの例:
 *
 * - "/subdir/index.html" => "/subdir/"
 * - "/subdir/index.php/" => "/subdir/"
 * - "/subdir/logo.png/"  => "/subdir/logo.png"
 * - "/subdir/var"        => "/subdir/var/"
 */
class CanonicalPathResolver implements Resolver
{
    /**
     * 正規化が不要な場合に移譲する、本来の処理を担当する Resolver です。
     *
     * @var Resolver
     */
    private $mainResolver;

    /**
     * インデックスとして扱うファイル名のパターンの配列です。
     *
     * @var string[]
     */
    private $indexPatterns;

    /**
     * インスタンスを初期化します。
     *
     * @param Resolver $mainResolver 本来の処理を担当する Resolver
     * @param string[] $indexPatterns インデックスのパターン (デフォルトは ["index.php", "index.html"])
     */
    public function __construct(Resolver $mainResolver, array $indexPatterns = ["index.php", "index.html"])
    {
        $this->mainResolver  = $mainResolver;
        $this->indexPatterns = $indexPatterns;
    }

    /**
     * ルーティングコンテキストを評価し、必要に応じてリダイレクト用の Target を返します。
     *
     * @param RoutingContext $context 現在のルーティングコンテキスト
     * @return Target 解決された Target オブジェクト
     */
    public function resolve(RoutingContext $context): Target
    {
        $segments = $context->getSegments();
        $count    = count($segments);
        if ($count === 0) {
            return $this->mainResolver->resolve($context);
        }

        $lastSegment = $segments[$count - 1];
        $appPath     = $context->getAppPath();
        if ($this->detectIndexPattern($lastSegment)) {
            return $this->buildRedirectTarget($appPath, strlen($lastSegment));
        }
        if ($this->detectFilePattern($lastSegment)) {
            return $this->mainResolver->resolve($context);
        }
        if ($lastSegment === "") {
            return $this->resolveEmptyLastSegment($context, $segments, $count, $appPath);
        }

        return $this->buildRedirectTarget($appPath . "/", 0);
    }

    /**
     * セグメントの末尾が空文字列だった場合 (URL が "/" で終わっている場合) の解決ロジックです。
     *
     * @param RoutingContext $context
     * @param string[] $segments
     * @param int $count
     * @param string $appPath
     * @return Target
     */
    private function resolveEmptyLastSegment(RoutingContext $context, array $segments, int $count, string $appPath): Target
    {
        if ($count < 2) {
            return $this->mainResolver->resolve($context);
        }

        $prevSegment = $segments[$count - 2];
        if ($this->detectIndexPattern($prevSegment)) {
            return $this->buildRedirectTarget($appPath, strlen($prevSegment) + 1);
        }
        if ($this->detectFilePattern($prevSegment)) {
            return $this->buildRedirectTarget($appPath, 1);
        }

        return $this->mainResolver->resolve($context);
    }

    /**
     * アプリケーションパスを指定された文字数だけ削り、RedirectController に解決する Target を返します。
     *
     * @param string $basePath 基準となるアプリケーションパス
     * @param int $trimLength 末尾から削る文字数
     * @return Target
     */
    private function buildRedirectTarget(string $basePath, int $trimLength): Target
    {
        $newPath = ($trimLength > 0) ? substr($basePath, 0, -$trimLength) : $basePath;
        return Target::found(new RedirectController($newPath, true));
    }

    /**
     * 指定されたセグメントがインデックスパターンかどうかを判定します。
     *
     * @param string $segment
     * @return bool
     */
    private function detectIndexPattern(string $segment): bool
    {
        return in_array($segment, $this->indexPatterns, true);
    }

    /**
     * 指定されたセグメントがファイル名パターン (1 文字以上の任意の文字, ドット ("."), 拡張子部分)
     * に合致するかどうかを判定します。
     *
     * @param string $segment
     * @return bool
     */
    private function detectFilePattern(string $segment): bool
    {
        return preg_match('/^.+\.[0-9a-zA-Z]+\z/', $segment) === 1;
    }
}
