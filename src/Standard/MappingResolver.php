<?php

namespace Warp\Standard;

use InvalidArgumentException;
use Warp\ControllerMapper;
use Warp\Resolver;
use Warp\RoutingContext;
use Warp\Target;

/**
 * 下位の Resolver の解決結果に対して、指定された ControllerMapper を透過的に適用する Resolver です。
 *
 * 複数の Mapper を配列として渡すことで、ミドルウェアのスタック (パイプライン) を構築することができます。
 */
class MappingResolver implements Resolver
{
    /**
     * @var Resolver
     */
    private $innerResolver;

    /**
     * @var ControllerMapper[]
     */
    private $mappers;

    /**
     * 対象の Resolver と、適用するマッパーを指定して初期化します。
     *
     * 第 2 引数のマッパーに ControllerMapper の配列を指定することができます。
     *
     * @param Resolver $innerResolver 本来の解決を担当する下位の Resolver
     * @param ControllerMapper|ControllerMapper[] $mapper 適用するマッパー
     * @throws InvalidArgumentException 不正なマッパーが指定された場合
     */
    public function __construct(Resolver $innerResolver, $mapper)
    {
        $this->innerResolver = $innerResolver;
        $this->mappers       = $this->normalizeMappers($mapper);
    }

    /**
     * コンストラクタに指定されたマッパーを検証し、ControllerMapper の配列に正規化して返します。
     *
     * @param mixed $mappers コンストラクタに指定された値
     * @return ControllerMapper[] ControllerMapper の配列
     * @throws InvalidArgumentException
     */
    private function normalizeMappers($mappers): array
    {
        if ($mappers instanceof ControllerMapper) {
            return [$mappers];
        }
        if (!is_array($mappers)) {
            throw new InvalidArgumentException("Mappers must be a ControllerMapper or an array of ControllerMappers.");
        }
        $normalized = [];
        foreach ($mappers as $mapper) {
            if (!$mapper instanceof ControllerMapper) {
                throw new InvalidArgumentException("All mappers must implement ControllerMapper.");
            }
            $normalized[] = $mapper;
        }
        return $normalized;
    }

    /**
     * 内部の Resolver で現在のルーティングコンテキストを評価し、宛先が見つかった場合は保持しているマッパーを順に適用して返します。
     *
     * @param RoutingContext $context 現在のルーティングコンテキスト
     * @return Target 解決された Target オブジェクト
     */
    public function resolve(RoutingContext $context): Target
    {
        $target = $this->innerResolver->resolve($context);
        foreach ($this->mappers as $mapper) {
            $target = $target->map($mapper);
        }
        return $target;
    }
}
