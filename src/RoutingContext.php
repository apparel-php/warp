<?php

namespace Warp;

use Woof\Http\Request;
use Woof\Web\WebEnvironment;
use Warp\Pattern\PathPattern;

/**
 * 現在のルーティング状態を保持するイミュータブルなコンテキストオブジェクトです。
 */
class RoutingContext
{
    /**
     * 処理対象の HTTP リクエストです。
     *
     * @var Request
     */
    private $request;

    /**
     * Web アプリケーションの実行環境です。
     *
     * @var WebEnvironment
     */
    private $env;

    /**
     * 未処理のパスセグメントの配列です。
     *
     * @var string[]
     */
    private $segments;

    /**
     * リクエストのアプリケーションパスです。
     *
     * @var string
     */
    private $appPath;

    /**
     * 指定された HTTP リクエスト, WebEnvironment, 未処理のパスセグメント配列, アプリケーションパスを持つ
     * RoutingContext オブジェクトを構築します。
     *
     * このクラスは create() または shift() を使ってインスタンス化されるため、
     * コンストラクタが直接実行される機会はありません。
     *
     * @param Request $request HTTP リクエスト
     * @param WebEnvironment $env Web アプリケーションの実行環境
     * @param string[] $segments 未処理のパスセグメント配列
     * @param string $appPath アプリケーションパス
     */
    private function __construct(Request $request, WebEnvironment $env, array $segments, string $appPath)
    {
        $this->request  = $request;
        $this->env      = $env;
        $this->segments = array_values($segments);
        $this->appPath  = $appPath;
    }

    /**
     * リクエストの URL パスを解析し、初期状態の RoutingContext を生成します。
     *
     * リクエストのパスは下記の要領でセグメント列に分解されます。
     *
     * - 連続するセパレーター ("/") は 1 つにまとめられます
     * - WebEnvironment の Config から `app.root-path` を読み取り、パスの先頭から除外します
     * - URL のパスが "/" で終わる場合、空文字列 ("") を末尾のセグメントとして保持します
     *
     * @param Request $request HTTP リクエスト
     * @param WebEnvironment $env Web アプリケーションの実行環境
     * @return self 構築された初期状態のルーティングコンテキスト
     * @throws RootPathMismatchException リクエストパスが app.root-path と一致しない場合
     */
    public static function create(Request $request, WebEnvironment $env): self
    {
        $path     = self::formatRequestPath($request->getPath());
        $rootPath = self::formatRootPath($env->getConfig()->getString("app.root-path", ""));
        if ($rootPath !== "" && strpos($path, $rootPath) !== 0) {
            throw new RootPathMismatchException("Request path '{$path}' does not match the app.root-path '{$rootPath}'.");
        }

        $appPath = substr($path, strlen($rootPath));
        if ($appPath === "") {
            $appPath = "/";
        }

        $relativePath = substr($appPath, 1);
        $segments     = explode("/", $relativePath);
        return new self($request, $env, $segments, $appPath);
    }

    /**
     * リクエストパスの正規化を行います。
     * 空文字列または先頭が `/` でない場合はスラッシュを付与し、連続するスラッシュは 1 つにまとめます。
     *
     * @param string $path 前処理対象のパス
     * @return string 正規化されたパス
     */
    private static function formatRequestPath(string $path): string
    {
        if ($path === "" || $path[0] !== "/") {
            $path = "/" . $path;
        }
        return preg_replace("#/+#", "/", $path);
    }

    /**
     * ルートパス (app.root-path) の正規化を行います。
     * 空文字列の場合はそのまま返します。
     *
     * @param string $path 前処理対象のベースパス
     * @return string 正規化されたベースパス
     */
    private static function formatRootPath(string $path): string
    {
        if ($path === "") {
            return "";
        }
        if ($path[0] !== "/") {
            $path = "/" . $path;
        }
        return rtrim(preg_replace("#/+#", "/", $path), "/");
    }

    /**
     * 未処理のパスセグメントが残っているかどうかを調べます。
     *
     * @return bool 未処理のセグメントが 1 つ以上残っている場合に true
     */
    public function hasNextSegment(): bool
    {
        return count($this->segments) > 0;
    }

    /**
     * 現在評価対象となっている先頭のパスセグメントを取得します。
     *
     * パスの末尾がスラッシュで終わっている場合の最終セグメントか、
     * または未処理のセグメントが残っていない場合は空文字列を返します。
     *
     * @return string 先頭のパスセグメント、または空文字列
     */
    public function peek(): string
    {
        return $this->segments[0] ?? "";
    }

    /**
     * 先頭のパスセグメントを 1 つ消費した、新しい RoutingContext を生成して返します。
     *
     * @return self パスが 1 つ進んだ新しいルーティングコンテキスト
     * @throws NoMoreSegmentException 未処理のセグメントが残っていない状態で実行された場合
     */
    public function shift(): self
    {
        if (!$this->hasNextSegment()) {
            throw new NoMoreSegmentException("No more segments to shift");
        }

        $newSegments = $this->segments;
        array_shift($newSegments);
        return new self($this->request, $this->env, $newSegments, $this->appPath);
    }

    /**
     * 処理対象の HTTP リクエストを取得します。
     *
     * @return Request 処理対象の HTTP リクエスト
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Web アプリケーションの実行環境をあらわす WebEnvironment オブジェクトを取得します。
     *
     * @return WebEnvironment Web 環境オブジェクト
     */
    public function getEnvironment(): WebEnvironment
    {
        return $this->env;
    }

    /**
     * ルーティング対象のアプリケーションパスを取得します。
     *
     * @return string アプリケーションパス
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * HTTP リクエストメソッドを取得します。
     *
     * @return string HTTP メソッド (例: "get", "post")
     */
    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    /**
     * 指定された名前の GET パラメータ (クエリ) を取得します。
     *
     * @param string $name パラメータ名
     * @param mixed $defaultValue 存在しない場合の代替値
     * @return mixed 指定されたパラメータの値または代替値
     */
    public function getQuery(string $name, $defaultValue = null)
    {
        return $this->request->getQuery($name, $defaultValue);
    }

    /**
     * 指定された名前の POST パラメータを取得します。
     *
     * @param string $name パラメータ名
     * @param mixed $defaultValue 存在しない場合の代替値
     * @return mixed 指定されたパラメータの値または代替値
     */
    public function getPost(string $name, $defaultValue = null)
    {
        return $this->request->getPost($name, $defaultValue);
    }

    /**
     * 未処理のパスセグメントの配列を取得します。
     *
     * @return string[] 未処理のパスセグメントの配列
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * 現在の未処理セグメント列から、部分パスを復元して取得します。
     * セグメントをすべて処理し終わっている場合は空文字列を返します。
     *
     * @return string 復元された部分パス (例: "/subdir/subpage/", セグメントがない場合は "")
     */
    public function getPartialPath(): string
    {
        return (count($this->segments) === 0) ? "" : "/" . implode("/", $this->segments);
    }

    /**
     * 現在の部分パスが指定されたテンプレートにマッチするかどうかを調べます。
     *
     * テンプレート内には {id} や {name} のようなプレースホルダを記述することができます。
     * マッチした場合はプレースホルダの値を保持する成功状態の RouteMatch オブジェクトを返し、
     * マッチしなかった場合は失敗状態の RouteMatch オブジェクトを返します。
     *
     * @param string $template マッチングに使用するテンプレート文字列
     * @return RouteMatch マッチ結果を保持するオブジェクト
     */
    public function matchPath(string $template): RouteMatch
    {
        return PathPattern::parse($template)->match($this->getPartialPath());
    }
}
