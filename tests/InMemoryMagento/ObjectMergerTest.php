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
                ['sku' => 'sku1', 'custom_attributes' => [['attribute_code' => 'description', 'value' => 'desc1']]]
            ),
            $this->object(
                ['sku' => 'sku2', 'custom_attributes' => [['attribute_code' => 'description', 'value' => '']]]
            )
        );
        $this->assertEquals('sku2', $merge->sku);
        $this->assertTrue(is_array($merge->custom_attributes));
        $this->assertCount(1, $merge->custom_attributes);
        $this->assertEquals('description', $merge->custom_attributes[0]->attribute_code);
        $this->assertEquals('', $merge->custom_attributes[0]->value);
    }

    public function testAddNewCustomAttributes(): void
    {
        $merge = ObjectMerger::merge(
            $this->object(
                [
                    'sku' => 'sku1',
                    'custom_attributes' => [
                        ['attribute_code' => 'description', 'value' => 'desc1']
                    ]
                ]
            ),
            $this->object(
                [
                    'sku' => 'sku2',
                    'custom_attributes' => [
                        ['attribute_code' => 'other_attr', 'value' => 'other_value']
                    ]
                ]
            )
        );

        $this->assertEquals('sku2', $merge->sku);
        $this->assertTrue(is_array($merge->custom_attributes));
        $this->assertCount(2, $merge->custom_attributes);
        $this->assertEquals('description', $merge->custom_attributes[0]->attribute_code);
        $this->assertEquals('desc1', $merge->custom_attributes[0]->value);
        $this->assertEquals('other_attr', $merge->custom_attributes[1]->attribute_code);
        $this->assertEquals('other_value', $merge->custom_attributes[1]->value);
    }

    public function testMergeCustomerGroupPrices(): void
    {
        $this->markTestIncomplete('TODO: Make it green');
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
