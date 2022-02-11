<?php
declare(strict_types=1);

namespace Phper666\JWTAuth;

use FastRoute\RouteParser\Std;

/**
 * 此类是根据nikic/fastroute包提供的路由匹配而修改的
 * 里面修改了一些路由匹配逻辑，可以支持hyperf路由正则等配置
 * 可以直接在no_check_route里面配置正则路由忽略掉不需要检查的路由
 */
class PathMatch
{
    protected function getApproxChunkSize(): int
    {
        return 10;
    }

    protected function computeChunkSize($count)
    {
        $numParts = max(1, round($count / $this->getApproxChunkSize()));
        return (int) ceil($count / $numParts);
    }

    protected function processChunk($regexToRoutesMap)
    {
        $routeMap = [];
        $regexes = [];
        $numGroups = 0;
        foreach ($regexToRoutesMap as $regex => $variables) {
            $numVariables = count($variables);
            $numGroups = max($numGroups, $numVariables);

            $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
            $routeMap[$numGroups + 1] = $variables;

            ++$numGroups;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';
        return ['regex' => $regex, 'routeMap' => $routeMap];
    }

    protected function buildRegexForRoute(array $parseRouteData): array
    {
        $regex = '';
        $variables = [];
        foreach ($parseRouteData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            [$varName, $regexPart] = $part;

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    public function matchRoute(array $noCheckRouteData, string $method, string $path): bool
    {
        // 为空直接返回路由不匹配
        if (empty($noCheckRouteData)) {
            return false;
        }

        $std = new Std();
        foreach ($noCheckRouteData as $route) {
            $noCheckMethod = $route[0] ?? null;
            $noCheckPath = $route[1] ?? null;
            if ($noCheckMethod == null || $noCheckPath == null) {
                // TODO 抛出异常
            }

            $noCheckMethod = strtoupper($noCheckMethod);
            $method = strtoupper($method);
            // 直接返回路由已经被匹配到
            if (($noCheckMethod == $method || $noCheckMethod == "**")) {
                if ($noCheckPath == "/**") {
                    return true;
                }

                if ($noCheckPath == $path) {
                    return true;
                }

                if ($noCheckPath != $path) {
                    // parse route
                    $parseRouteData = $std->parse($noCheckPath);
                    foreach ($parseRouteData as $routeData) {
                        [$regex, $variables] = $this->buildRegexForRoute($routeData);
                        $regexToRoutesMap[$regex] = $variables;
                        $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
                        $chunks = array_chunk($regexToRoutesMap, $chunkSize, true);
                        $buildRegexForRouteData = array_map([$this, 'processChunk'], $chunks);
                        foreach ($buildRegexForRouteData as $data) {
                            if (!preg_match($data['regex'], $path)) {
                                continue;
                            }
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
