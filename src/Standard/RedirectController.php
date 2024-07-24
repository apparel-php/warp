<?php

namespace Warp\Standard;

use Woof\Http\Request;
use Woof\Http\Response;
use Woof\Web\Controller;
use Woof\Web\Operator;
use Woof\Web\WebEnvironment;

/**
 * 指定されたアプリケーションパスへ 302 リダイレクトを行う Controller です。
 */
class RedirectController implements Controller
{
    /**
     * リダイレクト先のアプリケーションパスです。
     *
     * @var string
     */
    private $appPath;

    /**
     * リクエストの GET パラメータ (クエリ) を引き継ぐかどうかをあらわします。
     *
     * @var bool
     */
    private $preserveQuery;

    /**
     * リダイレクト先のパスと、クエリ引き継ぎの有無を指定して初期化します。
     *
     * @param string $appPath リダイレクト先のアプリケーションパス
     * @param bool $preserveQuery クエリを引き継ぐ場合は true (デフォルトは false)
     */
    public function __construct(string $appPath, bool $preserveQuery = false)
    {
        $this->appPath       = $appPath;
        $this->preserveQuery = $preserveQuery;
    }

    /**
     * 指定されたパスへリダイレクトする Response を返します。
     *
     * @param Request $request 処理対象の HTTP リクエスト
     * @param WebEnvironment $env Web アプリケーションの実行環境
     * @return Response リダイレクトを行う Response オブジェクト
     */
    public function handle(Request $request, WebEnvironment $env): Response
    {
        $queryList = $this->preserveQuery ? $request->getQueryList() : [];
        return (new Operator($request, $env))
            ->setRedirect($this->appPath, $queryList)
            ->build();
    }
}
