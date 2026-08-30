--TEST--
ref 001
--SKIPIF--
--FILE--
<?php

class TreeHelper
{
    public static function toTree(array $array, $idKey = 'id', $parentKey = 'pid', $childrenKey = 'children')
    {
        $tree       = [];
        $references = [];

        // 第一次遍历：创建所有节点的引用
        foreach ($array as &$node) {
            $node[$childrenKey]        = [];  // 初始化children
            $references[$node[$idKey]] = &$node;
        }
        unset($node);

        // 第二次遍历：构建树
        foreach ($array as &$node) {
            $parentId = $node[$parentKey];

            if (empty($parentId) || !isset($references[$parentId])) {
                // 根节点或父节点不存在
                $tree[] = &$node;
            } else {
                // 子节点
                $references[$parentId][$childrenKey][] = &$node;
            }
        }
        unset($node);

        return $tree;
    }
}

function main()
{
    // 测试数据
    $list = [
        ['id' => 1, 'pid' => 0, 'name' => '中国'],
        ['id' => 2, 'pid' => 1, 'name' => '广东省'],
        ['id' => 3, 'pid' => 1, 'name' => '浙江省'],
        ['id' => 4, 'pid' => 2, 'name' => '广州市'],
        ['id' => 5, 'pid' => 2, 'name' => '深圳市'],
        ['id' => 6, 'pid' => 3, 'name' => '杭州市'],
        ['id' => 7, 'pid' => 0, 'name' => '美国'],
        ['id' => 8, 'pid' => 7, 'name' => '加州'],
        ['id' => 9, 'pid' => 8, 'name' => '旧金山'],
    ];

    // 调用
    $tree = TreeHelper::toTree($list);

    // 打印结果
    echo "生成的树结构：\n";
    print_r($tree);

    // 简单断言测试
    echo "\n开始断言测试...\n";

    // 根节点应该有 2 个：中国、美国
    assert(count($tree) === 2);

    // 第一个根节点是中国
    assert($tree[0]['name'] === '中国');

    // 中国下面应该有两个省
    assert(count($tree[0]['children']) === 2);
    assert($tree[0]['children'][0]['name'] === '广东省');
    assert($tree[0]['children'][1]['name'] === '浙江省');

    // 广东省下面应该有两个城市
    assert(count($tree[0]['children'][0]['children']) === 2);
    assert($tree[0]['children'][0]['children'][0]['name'] === '广州市');
    assert($tree[0]['children'][0]['children'][1]['name'] === '深圳市');

    // 浙江省下面应该有一个城市
    assert(count($tree[0]['children'][1]['children']) === 1);
    assert($tree[0]['children'][1]['children'][0]['name'] === '杭州市');

    // 第二个根节点是美国
    assert($tree[1]['name'] === '美国');

    // 美国 -> 加州 -> 旧金山
    assert(count($tree[1]['children']) === 1);
    assert($tree[1]['children'][0]['name'] === '加州');
    assert(count($tree[1]['children'][0]['children']) === 1);
    assert($tree[1]['children'][0]['children'][0]['name'] === '旧金山');

    echo "DONE\n";
}
?>
--EXPECT--
生成的树结构：
Array
(
    [0] => Array
        (
            [id] => 1
            [pid] => 0
            [name] => 中国
            [children] => Array
                (
                    [0] => Array
                        (
                            [id] => 2
                            [pid] => 1
                            [name] => 广东省
                            [children] => Array
                                (
                                    [0] => Array
                                        (
                                            [id] => 4
                                            [pid] => 2
                                            [name] => 广州市
                                            [children] => Array
                                                (
                                                )

                                        )

                                    [1] => Array
                                        (
                                            [id] => 5
                                            [pid] => 2
                                            [name] => 深圳市
                                            [children] => Array
                                                (
                                                )

                                        )

                                )

                        )

                    [1] => Array
                        (
                            [id] => 3
                            [pid] => 1
                            [name] => 浙江省
                            [children] => Array
                                (
                                    [0] => Array
                                        (
                                            [id] => 6
                                            [pid] => 3
                                            [name] => 杭州市
                                            [children] => Array
                                                (
                                                )

                                        )

                                )

                        )

                )

        )

    [1] => Array
        (
            [id] => 7
            [pid] => 0
            [name] => 美国
            [children] => Array
                (
                    [0] => Array
                        (
                            [id] => 8
                            [pid] => 7
                            [name] => 加州
                            [children] => Array
                                (
                                    [0] => Array
                                        (
                                            [id] => 9
                                            [pid] => 8
                                            [name] => 旧金山
                                            [children] => Array
                                                (
                                                )

                                        )

                                )

                        )

                )

        )

)

开始断言测试...
DONE