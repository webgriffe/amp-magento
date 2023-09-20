<?php

declare(strict_types=1);

namespace Webgriffe\AmpMagento\Tests\InMemoryMagento;

use PHPUnit\Framework\TestCase;
use Webgriffe\AmpMagento\InMemoryMagento\ObjectMerger;
use Webgriffe\AmpMagento\InMemoryMagento\Utils;

class ObjectMergerTest extends TestCase
{
    use Utils;

    public function testSimpleMerge(): void
    {
        $merge = ObjectMerger::merge($this->object(['sku' => 'sku1']), $this->object(['sku' => 'sku2']));
        $this->assertEquals('sku2', $merge->sku);
    }

    public function testRecursiveMerge(): void
    {
        $merge = ObjectMerger::merge(
            $this->object(
                ['sku' => 'sku1', 'extension_attributes' => ['stock_item' => ['qty' => 1, 'is_in_stock' => true]]]
            ),
            $this->object(
                ['sku' => 'sku2', 'extension_attributes' => ['stock_item' => ['qty' => 10]]]
            )
        );
        $this->assertEquals('sku2', $merge->sku);
        $this->assertEquals(10, $merge->extension_attributes->stock_item->qty);
        $this->assertEquals(true, $merge->extension_attributes->stock_item->is_in_stock);
    }

    public function testMergeSameCustomAttributesByAttributeCode(): void
    {
        $merge = ObjectMerger::merge(
            $this->object(
                [
                    'sku' => 'sku1',
                    'custom_attributes' => [
                        ['attribute_code' => 'description', 'value' => 'desc1'],
                        ['attribute_code' => 'another_attribute', 'value' => 'a value'],
                    ]
                ]
            ),
            $this->object(
                [
                    'sku' => 'sku1',
                    'custom_attributes' => [
                        ['attribute_code' => 'description', 'value' => ''],
                        ['attribute_code' => 'new_attribute', 'value' => 'new value'],
                    ]
                ]
            )
        );

        $this->assertEquals(
            [
                $this->object(['attribute_code' => 'description', 'value' => '']),
                $this->object(['attribute_code' => 'another_attribute', 'value' => 'a value']),
                $this->object(['attribute_code' => 'new_attribute', 'value' => 'new value']),
            ],
            $merge->custom_attributes
        );
    }

    public function testMergeCustomAttributesWithNoCustomAttributesOnFirstObject(): void
    {
        $merge = ObjectMerger::merge(
            $this->object(
                [
                    'sku' => 'sku1'
                ]
            ),
            $this->object(
                [
                    'sku' => 'sku1',
                    'custom_attributes' => [
                        ['attribute_code' => 'description', 'value' => ''],
                    ]
                ]
            )
        );

        $this->assertEquals(
            [
                $this->object(['attribute_code' => 'description', 'value' => '']),
            ],
            $merge->custom_attributes
        );
    }

    public function testMergeCustomerGroupTierPrices(): void
    {
        $merge = ObjectMerger::merge(
            $this->object(
                [
                    'sku' => 'aSKU',
                    'name' => 'A name',
                    'type_id' => 'simple',
                    'attribute_set_id' => 4,
                    'price' => '12',
                    'tier_prices' => [
                        [
                            'customer_group_id' => 3,
                            'qty' => 1,
                            'value' => 10,
                        ],
                        [
                            'customer_group_id' => 4,
                            'qty' => 1,
                            'value' => 10,
                        ]
                    ]
                ]
            ),
            $this->object(
                [
                    'sku' => 'aSKU',
                    'name' => 'A name',
                    'type_id' => 'simple',
                    'attribute_set_id' => 4,
                    'price' => '12',
                    'tier_prices' => [
                        [
                            'customer_group_id' => 4,
                            'qty' => 1,
                            'value' => 100,
                        ]
                    ]
                ]
            )
        );

        $this->assertTrue(is_array($merge->tier_prices));
        $this->assertCount(1, $merge->tier_prices);
        $this->assertEquals('100', $merge->tier_prices[0]->value);
        $this->assertEquals('1', $merge->tier_prices[0]->qty);
        $this->assertEquals('4', $merge->tier_prices[0]->customer_group_id);
    }
}
