<?php

namespace Webgriffe\AmpMagento\Tests;

use PHPUnit\Framework\TestCase;
use Webgriffe\AmpMagento\ApiClient;
use Webgriffe\AmpMagento\InMemoryMagento\HttpClient;
use Webgriffe\AmpMagento\InMemoryMagento\Routes;
use Webgriffe\AmpMagento\InMemoryMagento\Server;
use Webgriffe\AmpMagento\InMemoryMagento\Utils;
use function Amp\Promise\wait;

class ApiClientTest extends TestCase
{
    use Utils;
    /**
     * @var ApiClient
     */
    private $client;

    public function setUp()
    {
        $config = [
            'baseUrl' => 'http://my-url',
            'username' => 'admin',
            'password' => 'password123'
        ];
        $schemaJson = file_get_contents(__DIR__ . '/mage22-schema.json');
        $inMemoryMagento = new Server($schemaJson, new Routes());
        $fakeClient = new HttpClient($inMemoryMagento);
        $this->client = new ApiClient($fakeClient, $config);
    }

    public function testGetNotExistingProduct()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'extension_attributes' => ['stock_item' => ['qty' => 100, 'is_in_stock' => true]]
            ]
        );

        $notExistingProduct = wait($this->client->getProduct('SKU-NOT-EXISTING'));

        $this->assertNull($notExistingProduct);
    }

    public function testShouldGetExistingProduct()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'extension_attributes' => ['stock_item' => ['qty' => 100, 'is_in_stock' => true]]
            ]
        );

        $product = wait($this->client->getProduct('SKU-123'));

        $this->assertNotNull($product);
        $this->assertEquals('Product Name', $product['name']);
        $this->assertEquals(4, $product['attribute_set_id']);
        $this->assertEquals(100, $product['extension_attributes']['stock_item']['qty']);
    }

    public function testShouldGetProductsWithFilters()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple'
            ]
        );
        Routes::$products['SKU-234'] = $this->object(
            [
                'sku' => 'SKU-234',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'configurable'
            ]
        );

        $products = wait(
            $this->client->getProducts([['field' => 'type_id', 'value' => 'configurable', 'condition' => 'eq']])
        );

        $this->assertCount(1, $products['items']);
    }

    public function testShouldGetProductsWithComplexFilters()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Good Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple'
            ]
        );
        Routes::$products['SKU-234'] = $this->object(
            [
                'sku' => 'SKU-234',
                'name' => 'Bad Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple'
            ]
        );

        Routes::$products['SKU-345'] = $this->object(
            [
                'sku' => 'SKU-345',
                'name' => 'Very Good Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'configurable'
            ]
        );

        $products = wait(
            $this->client->getProducts(
                [
                    ['field' => 'type_id', 'value' => 'simple', 'condition' => 'eq'],
                    ['field' => 'name', 'value' => 'Good Product Name', 'condition' => 'like']
                ]
            )
        );

        $this->assertCount(1, $products['items']);
    }

    public function testShouldCreateProduct()
    {
        $this->assertCount(0, Routes::$products);

        $productData = [
            'product' => [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'price' => 10
            ]
        ];
        $createdProduct = wait($this->client->createProduct($productData));

        $this->assertCount(1, Routes::$products);
        $this->assertEquals('SKU-123', $createdProduct['sku']);
        $this->assertEquals('Product Name', $createdProduct['name']);
        $this->assertEquals(10, $createdProduct['price']);
    }

    public function testShouldThrowExceptionCreatingProductWithoutMandatoryData()
    {
        $this->assertCount(0, Routes::$products);

        $productData = [
            'product' => [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
            ]
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Unexpected response status (400) with error message "The value of attribute "price" must be set.'
        );

        wait($this->client->createProduct($productData));
    }

    public function testShouldUpdateExistingProduct()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'extension_attributes' => ['stock_item' => ['qty' => 100, 'is_in_stock' => true]]
            ]
        );

        $productData = ['product' => ['name' => 'New Name']];
        wait($this->client->updateProduct('SKU-123', $productData));

        $this->assertEquals('New Name', Routes::$products['SKU-123']->name);
    }

    public function testShouldUpdateTranslatedAttributesForExistingProduct()
    {
        $this->markTestIncomplete('TODO: This should be tested');
    }

    public function testShouldGetAllProductAttributes()
    {
        Routes::$productAttributes =  [
            'description' => $this->object(
                [
                    'attribute_id' => '1',
                    'attribute_code' => 'description',
                    'default_frontend_label' => 'Description',
                    'options' => [],
                ]
            ),
            'material' => $this->object(
                [
                    'attribute_id' => '101',
                    'attribute_code' => 'material',
                    'default_frontend_label' => 'Material',
                    'options' => [],
                ]
            ),
            'composition' => $this->object(
                [
                    'attribute_id' => '102',
                    'attribute_code' => 'composition',
                    'default_frontend_label' => 'Composition',
                    'options' => [],
                ]
            ),
            'size' => $this->object(
                [
                    'attribute_id' => '103',
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'options' => [
                        ['label' => ' ', 'value' => ''],
                        ['label' => 'Small', 'value' => '1'],
                        ['label' => 'Medium', 'value' => '2'],
                        ['label' => 'Large', 'value' => '3']
                    ],
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Table'
                ]
            )
        ];

        $foundAttributes = wait($this->client->getAllProductAttributes());

        $this->assertCount(4, $foundAttributes['items']);
    }

    public function testShouldGetAllProductAttributesForTheGivenStoreView()
    {
        $this->markTestIncomplete('TODO: This should be tested');
    }

    public function testShouldGetProductAttributeByAttributeCode()
    {
        Routes::$productAttributes =  [
            'description' => $this->object(
                [
                    'attribute_id' => '1',
                    'attribute_code' => 'description',
                    'default_frontend_label' => 'Description',
                    'options' => [],
                ]
            ),
            'material' => $this->object(
                [
                    'attribute_id' => '101',
                    'attribute_code' => 'material',
                    'default_frontend_label' => 'Material',
                    'options' => [],
                ]
            ),
            'composition' => $this->object(
                [
                    'attribute_id' => '102',
                    'attribute_code' => 'composition',
                    'default_frontend_label' => 'Composition',
                    'options' => [],
                ]
            ),
            'size' => $this->object(
                [
                    'attribute_id' => '103',
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'options' => [
                        ['label' => ' ', 'value' => ''],
                        ['label' => 'Small', 'value' => '1'],
                        ['label' => 'Medium', 'value' => '2'],
                        ['label' => 'Large', 'value' => '3']
                    ],
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Table'
                ]
            )
        ];

        $foundAttributes = wait($this->client->getProductAttributeByCode('composition'));

        $this->assertCount(1, $foundAttributes['items']);
        $this->assertEquals('102', $foundAttributes['items'][0]['attribute_id']);
    }

    public function testShouldGetCategoriesByAttribute()
    {
        Routes::$categories[] = $this->object(
            [
                'id' => '500',
                'name' => 'Man',
                'path' => '1/2/300/400/500',
                'custom_attributes' => [['attribute_code' => 'sizeguide-type', 'value' => 'sizeguide-man']]
            ]
        );

        Routes::$categories[] = $this->object(
            [
                'id' => '600',
                'name' => 'Woman',
                'path' => '1/2/300/400/600',
                'custom_attributes' => [['attribute_code' => 'sizeguide-type', 'value' => 'sizeguide-woman']]
            ]
        );

        $foundCategories = wait($this->client->getCategoriesByAttribute('sizeguide-type', 'sizeguide-woman'));

        $this->assertCount(1, $foundCategories['items']);
        $this->assertEquals(1, $foundCategories['total_count']);
        $this->assertEquals('Woman', $foundCategories['items'][0]['name']);
    }

    public function testShouldCreateProductAttributeOptions()
    {
        Routes::$productAttributes =  [
            'size' => $this->object(
                [
                    'attribute_id' => '103',
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'options' => [
                        ['label' => ' ', 'value' => ''],
                        ['label' => 'Small', 'value' => '1'],
                        ['label' => 'Medium', 'value' => '2'],
                        ['label' => 'Large', 'value' => '3']
                    ],
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Table'
                ]
            )
        ];

        wait($this->client->createProductAttributeOption('size', 'Extra Large'));

        $this->assertCount(5, Routes::$productAttributes['size']->options);
        $this->assertEquals('Extra Large', Routes::$productAttributes['size']->options[4]->label);
    }

    public function testShouldGetAllProductAttributeOptions()
    {
        Routes::$productAttributes =  [
            'size' => $this->object(
                [
                    'attribute_id' => '103',
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'options' => [
                        ['label' => ' ', 'value' => ''],
                        ['label' => 'Small', 'value' => '1'],
                        ['label' => 'Medium', 'value' => '2'],
                        ['label' => 'Large', 'value' => '3']
                    ],
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Table'
                ]
            )
        ];

        $foundAttributeOptions = wait($this->client->getAllProductAttributeOptions('size'));

        $this->assertCount(4, $foundAttributeOptions);
    }


    public function testShouldLinkSimpleProductToConfigurable()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'id' => 1,
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'extension_attributes' => ['stock_item' => ['qty' => 100, 'is_in_stock' => true]]
            ]
        );

        Routes::$products['PARENT-123'] = $this->object(
            [
                'id' => 2,
                'sku' => 'PARENT-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'configurable',
                'extension_attributes' => [
                    'configurable_product_links' => []
                ]
            ]
        );

        wait($this->client->linkChildProductToConfigurable('SKU-123', 'PARENT-123'));

        $this->assertEquals([1], Routes::$products['PARENT-123']->extension_attributes->configurable_product_links);
    }

    public function testShouldGetAllInvoicesIfNoDateIsNotSpecified()
    {
        Routes::$invoices = [
            $this->object(['order_id' => 123, 'created_at' => '2014-12-12 00:00:00']),
            $this->object(['order_id' => 456, 'created_at' => '2015-12-12 00:00:00']),
            $this->object(['order_id' => 789, 'created_at' => '2016-12-12 00:00:00'])
        ];

        $invoices = wait($this->client->getInvoices());

        $this->assertCount(3, $invoices['items']);
    }

    public function testShouldGetInvoicesCreatedAfterAGivenDate()
    {
        Routes::$invoices = [
            $this->object(['order_id' => 123, 'created_at' => '2014-12-12 00:00:00']),
            $this->object(['order_id' => 456, 'created_at' => '2015-12-12 00:00:00']),
            $this->object(['order_id' => 789, 'created_at' => '2016-12-12 00:00:00'])
        ];

        $invoices = wait($this->client->getInvoices('2016-01-01 00:00:00'));

        $this->assertCount(1, $invoices['items']);
    }

    public function testShouldGetAllOrdersIfNoFilterIsSpecified()
    {
        Routes::$orders = [
            $this->object(
                [
                    'id' => 1,
                    'increment_id' => '100000001',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '35',
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt'],
                        ['item_id' => 2, 'name' => 'Baseball cap']
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 2,
                    'increment_id' => '100000002',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '20',
                    'items' => [
                        ['item_id' => 3, 'name' => 'T-Shirt'],
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 3,
                    'increment_id' => '100000003',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '15',
                    'items' => [
                        ['item_id' => 4, 'name' => 'Baseball cap']
                    ]
                ]
            ),
        ];

        $orders = wait($this->client->getOrders());

        $this->assertCount(3, $orders['items']);
    }

    public function testShouldGetAllOrdersMatchingSpecificFilters1()
    {
        Routes::$orders = [
            $this->object(
                [
                    'id' => 1,
                    'increment_id' => '100000001',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '35',
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt'],
                        ['item_id' => 2, 'name' => 'Baseball cap']
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 2,
                    'increment_id' => '100000002',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '20',
                    'items' => [
                        ['item_id' => 3, 'name' => 'T-Shirt'],
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 3,
                    'increment_id' => '100000003',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '15',
                    'items' => [
                        ['item_id' => 4, 'name' => 'Baseball cap']
                    ]
                ]
            ),
        ];

        $orders = wait($this->client->getOrders([['field' => 'total_paid', 'value' => '20', 'condition' => 'gt']]));

        $this->assertCount(1, $orders['items']);
    }

    public function testShouldGetAllOrdersMatchingSpecificFilters2()
    {
        Routes::$orders = [
            $this->object(
                [
                    'id' => 1,
                    'increment_id' => '100000001',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '35',
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt'],
                        ['item_id' => 2, 'name' => 'Baseball cap']
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 2,
                    'increment_id' => '100000002',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '20',
                    'items' => [
                        ['item_id' => 3, 'name' => 'T-Shirt'],
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 3,
                    'increment_id' => '100000003',
                    'base_currency_code' => 'EUR',
                    'total_paid' => '15',
                    'items' => [
                        ['item_id' => 4, 'name' => 'Baseball cap']
                    ]
                ]
            ),
        ];

        $orders = wait(
            $this->client->getOrders(
                [['field' => 'increment_id', 'value' => ['100000001', '100000003'], 'condition' => 'in']]
            )
        );

        $this->assertCount(2, $orders['items']);
    }

    public function testShoulGetOrder()
    {
        Routes::$orders['123'] = $this->object(
            [
                'id' => 123,
                'increment_id' => '100000123',
                'base_currency_code' => 'EUR',
                'total_paid' => '35',
                'items' => [
                    ['item_id' => 1, 'name' => 'T-Shirt'],
                    ['item_id' => 2, 'name' => 'Baseball cap']
                ]
            ]
        );

        $order = wait($this->client->getOrder(123));

        $this->assertEquals('100000123', $order['increment_id']);
    }

    public function testShouldCreateShipmentTrackForACompleteShipment()
    {
        $this->assertCount(0, Routes::$shipments);

        Routes::$orders = [
            '0000123' => $this->object(
                [
                    'status' => 'processing',
                    'items' => [['qty_ordered' => 1]]
                ]
            )
        ];

        $trackId = wait(
            $this->client->createShipmentTrack(
                '0000123',
                [
                    'items' => [['qty' => 1]],
                    'tracks' => [['track_number' => 'TRACK-123']],
                    'comment' => ['comment' => 'My comment']
                ]
            )
        );

        $this->assertCount(1, Routes::$shipments);
        $this->assertEquals(1, $trackId);
        $this->assertEquals('0000123', Routes::$shipments[1]->order_id);
        $this->assertEquals('My comment', Routes::$shipments[1]->comment);
        $this->assertEquals('TRACK-123', Routes::$shipments[1]->tracks[0]->track_number);
    }

    public function testCreateShipmentTrackShouldCompleteOrderIfItsACompleteShipmentAndOrderIsAlreadyInvoiced()
    {
        $this->assertCount(0, Routes::$shipments);

        Routes::$orders = [
            '0000123' => $this->object(
                [
                    'status' => 'processing',
                    'items' => [['qty_ordered' => 1]]
                ]
            )
        ];

        Routes::$invoices = [
            '100' => $this->object(
                [
                    'order_id' => '0000123',
                ]
            ),
        ];

        wait(
            $this->client->createShipmentTrack(
                '0000123',
                [
                    'items' => [['qty' => 1]],
                    'tracks' => [['track_number' => 'TRACK-123']],
                    'comment' => ['comment' => 'My comment']
                ]
            )
        );

        $this->assertEquals('complete', Routes::$orders['0000123']->status);
    }

    public function testCreateShipmentTrackShouldNotCompleteOrderIfItsAPartialShipment()
    {
        $this->assertCount(0, Routes::$shipments);

        Routes::$orders = [
            '0000123' => $this->object(
                [
                    'status' => 'processing',
                    'items' => [['qty_ordered' => 1], ['qty_ordered' => 1]]
                ]
            )
        ];

        wait(
            $this->client->createShipmentTrack(
                '0000123',
                [
                    'items' => [['qty' => 1]],
                    'tracks' => [['track_number' => 'TRACK-123']],
                    'comment' => ['comment' => 'My comment']
                ]
            )
        );

        $this->assertEquals('processing', Routes::$orders['0000123']->status);
    }

    public function testGetStockItem()
    {
        Routes::$stockItems['SKU-123'] = $this->object(['item_id' => 1, 'qty' => '3']);

        $stockItem = wait($this->client->getStockItem('SKU-123'));

        $this->assertEquals(['item_id' => 1, 'qty' => '3'], $stockItem);
    }

    public function testUpdateStockItemShouldThrowIfInvalidQtyIsGiven()
    {
        $this->expectException(\RuntimeException::class);

        wait($this->client->updateStockItem('product-123', ['stockItem' => ['item_id' => 1, 'qty' => '10,2']]));
    }

    public function testUpdateStockItemShouldUpdateStockItem()
    {
        $this->assertCount(0, Routes::$stockItems);

        Routes::$stockItems['product-123'] = $this->object(['item_id' => 1, 'qty' => '3']);

        $itemId = wait($this->client->updateStockItem('product-123', ['stockItem' => ['item_id' => 1, 'qty' => '10']]));

        $this->assertCount(1, Routes::$stockItems);
        $this->assertEquals(1, $itemId);
        $this->assertEquals(10, Routes::$stockItems['product-123']->qty);
    }
}
