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

    public const MAGENTO_SCHEMA_JSON_FILE = __DIR__ . '/mage24-schema.json';

    /** @var ApiClient */
    private $client;

    public function setUp(): void
    {
        $config = [
            'baseUrl' => 'http://my-url',
            'username' => 'admin',
            'password' => 'password123'
        ];
        $inMemoryMagento = new Server(realpath(self::MAGENTO_SCHEMA_JSON_FILE), new Routes());
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
                'extension_attributes' => [
                    'stock_item' => $this->getStockItemData(
                        [
                            'qty' => 100,
                            'is_in_stock' => true,
                        ]
                    ),
                ]
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
                'price' => 10,
                'weight' => 1.5,
            ]
        ];
        $createdProduct = wait($this->client->createProduct($productData));

        $this->assertCount(1, Routes::$products);
        $this->assertEquals('SKU-123', $createdProduct['sku']);
        $this->assertEquals('Product Name', $createdProduct['name']);
        $this->assertEquals(10, $createdProduct['price']);
        $this->assertEquals(1.5, $createdProduct['weight']);
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
                'extension_attributes' => [
                    'stock_item' => $this->getStockItemData(
                        [
                            'qty' => 100,
                            'is_in_stock' => true,
                        ]
                    ),
                ],
            ]
        );

        $productData = ['product' => ['sku' => 'SKU-123', 'name' => 'New Name',]];
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
                    'attribute_id' => 1,
                    'attribute_code' => 'description',
                    'default_frontend_label' => 'Description',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'material' => $this->object(
                [
                    'attribute_id' => 101,
                    'attribute_code' => 'material',
                    'default_frontend_label' => 'Material',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'composition' => $this->object(
                [
                    'attribute_id' => 102,
                    'attribute_code' => 'composition',
                    'default_frontend_label' => 'Composition',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'size' => $this->object(
                [
                    'attribute_id' => 103,
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'frontend_input' => 'select',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
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
                    'attribute_id' => 1,
                    'attribute_code' => 'description',
                    'default_frontend_label' => 'Description',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'material' => $this->object(
                [
                    'attribute_id' => 101,
                    'attribute_code' => 'material',
                    'default_frontend_label' => 'Material',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'composition' => $this->object(
                [
                    'attribute_id' => 102,
                    'attribute_code' => 'composition',
                    'default_frontend_label' => 'Composition',
                    'frontend_input' => 'text',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
                    'options' => [],
                ]
            ),
            'size' => $this->object(
                [
                    'attribute_id' => 103,
                    'attribute_code' => 'size',
                    'default_frontend_label' => 'Size',
                    'frontend_input' => 'select',
                    'entity_type_id' => 'product',
                    'is_required' => false,
                    'frontend_labels' => [],
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
                'id' => 500,
                'name' => 'Man',
                'path' => '1/2/300/400/500',
                'custom_attributes' => [['attribute_code' => 'sizeguide-type', 'value' => 'sizeguide-man']]
            ]
        );

        Routes::$categories[] = $this->object(
            [
                'id' => 600,
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
            $this->object([
                'order_id' => 123,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'ABC', 'order_item_id' => 10, 'qty' => 1]
                ],
                'created_at' => '2014-12-12 00:00:00'
            ]),
            $this->object([
                'order_id' => 456,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'DEF', 'order_item_id' => 20, 'qty' => 1]
                ],
                'created_at' => '2015-12-12 00:00:00'
            ]),
            $this->object([
                'order_id' => 789,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'GHI', 'order_item_id' => 30, 'qty' => 1]
                ],
                'created_at' => '2016-12-12 00:00:00'
            ])
        ];

        $invoices = wait($this->client->getInvoices());

        $this->assertCount(3, $invoices['items']);
    }

    public function testShouldGetInvoicesCreatedAfterAGivenDate()
    {
        Routes::$invoices = [
            $this->object([
                'order_id' => 123,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'ABC', 'order_item_id' => 10, 'qty' => 1]
                ],
                'created_at' => '2014-12-12 00:00:00'
            ]),
            $this->object([
                'order_id' => 456,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'DEF', 'order_item_id' => 20, 'qty' => 1]
                ],
                'created_at' => '2015-12-12 00:00:00'
            ]),
            $this->object([
                'order_id' => 789,
                'total_qty' => 1,
                'items' => [
                    ['sku' => 'GHI', 'order_item_id' => 30, 'qty' => 1]
                ],
                'created_at' => '2016-12-12 00:00:00'
            ])
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
                    'customer_email' => 'a@b.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 35,
                    'base_grand_total' => 35,
                    'grand_total' => 35,
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                        ['item_id' => 2, 'name' => 'Baseball cap', 'sku' => 'DEF']
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 2,
                    'increment_id' => '100000002',
                    'customer_email' => 'c@d.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 20,
                    'base_grand_total' => 20,
                    'grand_total' => 20,
                    'items' => [
                        ['item_id' => 3, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 3,
                    'increment_id' => '100000003',
                    'customer_email' => 'e@f.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 15,
                    'base_grand_total' => 15,
                    'grand_total' => 15,
                    'items' => [
                        ['item_id' => 4, 'name' => 'Baseball cap', 'sku' => 'DEF']
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
                    'customer_email' => 'a@b.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 35,
                    'base_grand_total' => 35,
                    'grand_total' => 35,
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                        ['item_id' => 2, 'name' => 'Baseball cap', 'sku' => 'DEF']
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
                    'customer_email' => 'a@b.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 35,
                    'base_grand_total' => 35,
                    'grand_total' => 35,
                    'items' => [
                        ['item_id' => 1, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                        ['item_id' => 2, 'name' => 'Baseball cap', 'sku' => 'DEF']
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 2,
                    'increment_id' => '100000002',
                    'customer_email' => 'c@d.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 20,
                    'base_grand_total' => 20,
                    'grand_total' => 20,
                    'items' => [
                        ['item_id' => 3, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                    ]
                ]
            ),
            $this->object(
                [
                    'id' => 3,
                    'increment_id' => '100000003',
                    'customer_email' => 'e@f.com',
                    'base_currency_code' => 'EUR',
                    'total_paid' => 15,
                    'base_grand_total' => 15,
                    'grand_total' => 15,
                    'items' => [
                        ['item_id' => 4, 'name' => 'Baseball cap', 'sku' => 'DEF']
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
                'customer_email' => 'a@b.com',
                'base_currency_code' => 'EUR',
                'total_paid' => 35,
                'base_grand_total' => 35,
                'grand_total' => 35,
                'items' => [
                    ['item_id' => 1, 'name' => 'T-Shirt', 'sku' => 'ABC'],
                    ['item_id' => 2, 'name' => 'Baseball cap', 'sku' => 'DEF']
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
                    'items' => [
                        [
                            'item_id' => 123,
                            'qty_ordered' => 1,
                            'qty_canceled' => 0,
                        ]
                    ]
                ]
            )
        ];

        $trackId = wait(
            $this->client->createShipmentTrack(
                '0000123',
                [
                    'items' => [
                        [
                            'order_item_id' => 123,
                            'qty' => 1
                        ]
                    ],
                    'tracks' => [
                        [
                            'title' => 'Courier',
                            'carrier_code' => 'UPS',
                            'track_number' => 'TRACK-123',
                        ]
                    ],
                    'comment' => [
                        'comment' => 'My comment',
                        'is_visible_on_front' => 0,
                    ],
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
                    'items' => [
                        [
                            'item_id' => 123,
                            'qty_ordered' => 1,
                            'qty_canceled' => 0,
                        ]
                    ]
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
                    'items' => [
                        [
                            'order_item_id' => 123,
                            'qty' => 1
                        ]
                    ],
                    'tracks' => [
                        [
                            'title' => 'Courier',
                            'carrier_code' => 'UPS',
                            'track_number' => 'TRACK-123',
                        ]
                    ],
                    'comment' => [
                        'comment' => 'My comment',
                        'is_visible_on_front' => 0,
                    ]
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
                    'items' => [
                        [
                            'item_id' => 123,
                            'qty_ordered' => 1,
                            'qty_canceled' => 0,
                        ],
                        [
                            'item_id' => 234,
                            'qty_ordered' => 1,
                            'qty_canceled' => 0,
                        ]
                    ]
                ]
            )
        ];

        wait(
            $this->client->createShipmentTrack(
                '0000123',
                [
                    'items' => [
                        [
                            'order_item_id' => 123,
                            'qty' => 1
                        ]
                    ],
                    'tracks' => [
                        [
                            'title' => 'Courier',
                            'carrier_code' => 'UPS',
                            'track_number' => 'TRACK-123',
                        ]
                    ],
                    'comment' => [
                        'comment' => 'My comment',
                        'is_visible_on_front' => 0,
                    ]
                ]
            )
        );

        $this->assertEquals('processing', Routes::$orders['0000123']->status);
    }

    public function testGetStockItem()
    {
        Routes::$stockItems['SKU-123'] = $this->object([
            'item_id' => 1,
            'qty' => 3,
            'is_in_stock' => true,
            'is_qty_decimal' => false,
            'show_default_notification_message' => false,
            'use_config_min_qty' => true,
            'min_qty' => 1,
            'use_config_min_sale_qty' => 1,
            'min_sale_qty' => 1,
            'use_config_max_sale_qty' => true,
            'max_sale_qty' => 999999999,
            'use_config_backorders' => true,
            'backorders' => 1,
            'use_config_notify_stock_qty' => true,
            'notify_stock_qty' => 1,
            'use_config_qty_increments' => true,
            'qty_increments' => 1,
            'use_config_enable_qty_inc' => true,
            'enable_qty_increments' => false,
            'use_config_manage_stock' => true,
            'manage_stock' => true,
            'low_stock_date' => '',
            'is_decimal_divided' => false,
            'stock_status_changed_auto' => 0,
        ]);

        $stockItem = wait($this->client->getStockItem('SKU-123'));

        $this->assertEquals(
            [
                'item_id' => 1,
                'qty' => 3,
                'is_in_stock' => true,
                'is_qty_decimal' => false,
                'show_default_notification_message' => false,
                'use_config_min_qty' => true,
                'min_qty' => 1,
                'use_config_min_sale_qty' => 1,
                'min_sale_qty' => 1,
                'use_config_max_sale_qty' => true,
                'max_sale_qty' => 999999999,
                'use_config_backorders' => true,
                'backorders' => 1,
                'use_config_notify_stock_qty' => true,
                'notify_stock_qty' => 1,
                'use_config_qty_increments' => true,
                'qty_increments' => 1,
                'use_config_enable_qty_inc' => true,
                'enable_qty_increments' => false,
                'use_config_manage_stock' => true,
                'manage_stock' => true,
                'low_stock_date' => '',
                'is_decimal_divided' => false,
                'stock_status_changed_auto' => 0,
            ],
            $stockItem
        );
    }

    public function testUpdateStockItemShouldThrowIfInvalidQtyIsGiven()
    {
        $this->expectException(\RuntimeException::class);

        wait($this->client->updateStockItem('product-123', ['stockItem' => ['item_id' => 1, 'qty' => '10,2']]));
    }

    public function testUpdateStockItemShouldUpdateStockItem()
    {
        $this->assertCount(0, Routes::$stockItems);

        Routes::$stockItems['product-123'] = $this->object([
            'item_id' => 1,
            'qty' => 3,
            'is_in_stock' => true,
            'is_qty_decimal' => false,
            'show_default_notification_message' => false,
            'use_config_min_qty' => true,
            'min_qty' => 1,
            'use_config_min_sale_qty' => 1,
            'min_sale_qty' => 1,
            'use_config_max_sale_qty' => true,
            'max_sale_qty' => 999999999,
            'use_config_backorders' => true,
            'backorders' => 1,
            'use_config_notify_stock_qty' => true,
            'notify_stock_qty' => 1,
            'use_config_qty_increments' => true,
            'qty_increments' => 1,
            'use_config_enable_qty_inc' => true,
            'enable_qty_increments' => false,
            'use_config_manage_stock' => true,
            'manage_stock' => true,
            'low_stock_date' => '',
            'is_decimal_divided' => false,
            'stock_status_changed_auto' => 0,
        ]);

        $itemId = wait($this->client->updateStockItem(
            'product-123',
            [
                'stockItem' => [
                    'item_id' => 1,
                    'qty' => 10,
                    'is_in_stock' => true,
                    'is_qty_decimal' => false,
                    'show_default_notification_message' => false,
                    'use_config_min_qty' => true,
                    'min_qty' => 1,
                    'use_config_min_sale_qty' => 1,
                    'min_sale_qty' => 1,
                    'use_config_max_sale_qty' => true,
                    'max_sale_qty' => 999999999,
                    'use_config_backorders' => true,
                    'backorders' => 1,
                    'use_config_notify_stock_qty' => true,
                    'notify_stock_qty' => 1,
                    'use_config_qty_increments' => true,
                    'qty_increments' => 1,
                    'use_config_enable_qty_inc' => true,
                    'enable_qty_increments' => false,
                    'use_config_manage_stock' => true,
                    'manage_stock' => true,
                    'low_stock_date' => '',
                    'is_decimal_divided' => false,
                    'stock_status_changed_auto' => 0,
                ]
            ]
        ));

        $this->assertCount(1, Routes::$stockItems);
        $this->assertEquals(1, $itemId);
        $this->assertEquals(10, Routes::$stockItems['product-123']->qty);
    }

    public function testCreateMediaGalleryImage()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'media_gallery_entries' => []
            ]
        );

        $newImageData = [
            'entry' => [
                'media_type' => 'image',
                'label' => 'file_name.jpg',
                'position' => 0,
                'disabled' => false,
                'types' => [],
                'content' => [
                    'base64_encoded_data' => base64_encode('this is the image data'),
                    'type' => 'image/jpeg',
                    'name' => 'file_name.jpg'
                ],
            ]
        ];
        wait($this->client->addProductMedia('SKU-123', $newImageData));

        $mediaGalleryEntries = Routes::$products['SKU-123']->media_gallery_entries;
        $this->assertCount(1, $mediaGalleryEntries);
        $image = reset($mediaGalleryEntries);
        $this->assertEquals(
            [
                'content' => 'this is the image data',
                'type' => 'image/jpeg',
                'name' => 'file_name.jpg'
            ],
            $image->testData
        );
    }

    public function testUpdateMediaGalleryImage()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
            ]
        );

        Routes::$products['SKU-123']->media_gallery_entries = [
            1 => $this->object(
                [
                    'id' => 1,
                    'media_type' => 'image',
                    'label' => 'SKU-123.jpg',
                    'position' => 0,
                    'disabled' => false,
                    'types' => [],
                    'file' => 'SKU-123.jpg',
                ]
            ),
        ];


        $newImageData = [
            'entry' => [
                'id' => 1,
                'media_type' => 'image',
                'label' => 'new_file_name.jpg',
                'position' => 0,
                'disabled' => false,
                'types' => [],
                'content' => [
                    'base64_encoded_data' => base64_encode('this is the new image data'),
                    'type' => 'image/jpeg',
                    'name' => 'new_file_name.jpg'
                ],
            ]
        ];
        wait($this->client->updateProductMedia('SKU-123', '1', $newImageData));

        $mediaGalleryEntries = Routes::$products['SKU-123']->media_gallery_entries;
        $this->assertCount(1, $mediaGalleryEntries);
        $image = reset($mediaGalleryEntries);
        $this->assertEquals(
            [
                'content' => 'this is the new image data',
                'type' => 'image/jpeg',
                'name' => 'new_file_name.jpg'
            ],
            $image->testData
        );
    }

    public function testCreateMediaGalleryVideo()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'media_gallery_entries' => []
            ]
        );

        $newVideoData = [
            'entry' => [
                'media_type' => 'external-video',
                'label' => 'my_video.mp4',
                'position' => 0,
                'disabled' => false,
                'types' => [],
                'content' => [
                    'base64_encoded_data' => base64_encode('this is the cover image'),
                    'type' => 'image/jpeg',
                    'name' => 'cover.jpg'
                ],
                'extension_attributes' => [
                    'video_content' => [
                        'media_type' => 'external-video',
                        'video_provider' => '',
                        'video_url' => 'https://player.vimeo.com/external/test.sd.mp4',
                        'video_title' => 'SKU-123',
                        'video_description' => '',
                        'video_metadata' => ''
                    ]
                ]
            ]
        ];
        wait($this->client->addProductMedia('SKU-123', $newVideoData));

        $mediaGalleryEntries = Routes::$products['SKU-123']->media_gallery_entries;
        $this->assertCount(1, $mediaGalleryEntries);
        $video = reset($mediaGalleryEntries);
        $this->assertEquals('external-video', $video->media_type);
        $this->assertEquals('my_video.mp4', $video->label);
        $this->assertEquals('external-video', $video->extension_attributes->video_content->media_type);
        $this->assertEquals(
            'https://player.vimeo.com/external/test.sd.mp4',
            $video->extension_attributes->video_content->video_url
        );
        $this->assertEquals('SKU-123', $video->extension_attributes->video_content->video_title);
        $this->assertEquals('', $video->extension_attributes->video_content->video_description);
        $this->assertEquals('', $video->extension_attributes->video_content->video_metadata);

        $this->assertEquals(
            [
                'content' => 'this is the cover image',
                'type' => 'image/jpeg',
                'name' => 'cover.jpg'
            ],
            $video->testData
        );
    }

    public function testUpdateMediaGalleryVideo()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'media_gallery_entries' => []
            ]
        );

        Routes::$products['SKU-123']->media_gallery_entries = [
            1 => $this->object(
                [
                    'media_type' => 'external-video',
                    'label' => 'my_video.mp4',
                    'position' => 0,
                    'disabled' => false,
                    'types' => [],
                    'content' => [
                        'base64_encoded_data' => base64_encode('this is the cover image'),
                        'type' => 'image/jpeg',
                        'name' => 'cover.jpg'
                    ],
                    'extension_attributes' => [
                        'video_content' => [
                            'media_type' => 'external-video',
                            'video_provider' => '',
                            'video_url' => 'https://player.vimeo.com/external/test.sd.mp4',
                            'video_title' => 'SKU-123',
                            'video_description' => '',
                            'video_metadata' => ''
                        ]
                    ]
                ]
            ),
        ];

        $newVideoData = [
            'entry' => [
                'id' => 1,
                'media_type' => 'external-video',
                'label' => 'another_video.mp4',
                'position' => 0,
                'disabled' => false,
                'types' => [],
                'content' => [
                    'base64_encoded_data' => base64_encode('this is another cover image'),
                    'type' => 'image/jpeg',
                    'name' => 'another_cover.jpg'
                ],
                'extension_attributes' => [
                    'video_content' => [
                        'media_type' => 'external-video',
                        'video_provider' => '',
                        'video_url' => 'https://player.vimeo.com/external/another_test.sd.mp4',
                        'video_title' => 'SKU-123',
                        'video_description' => '',
                        'video_metadata' => ''
                    ]
                ]
            ]
        ];
        wait($this->client->updateProductMedia('SKU-123', '1', $newVideoData));

        $mediaGalleryEntries = Routes::$products['SKU-123']->media_gallery_entries;
        $this->assertCount(1, $mediaGalleryEntries);
        $video = reset($mediaGalleryEntries);
        $this->assertEquals('external-video', $video->media_type);
        $this->assertEquals('another_video.mp4', $video->label);
        $this->assertEquals(
            'https://player.vimeo.com/external/another_test.sd.mp4',
            $video->extension_attributes->video_content->video_url
        );
        $this->assertEquals(
            [
                'content' => 'this is another cover image',
                'type' => 'image/jpeg',
                'name' => 'another_cover.jpg'
            ],
            $video->testData
        );
    }

    public function testDeleteMediaGalleryEntry()
    {
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'media_gallery_entries' => []
            ]
        );

        Routes::$products['SKU-123']->media_gallery_entries = [
            1 => $this->object(
                [
                    'media_type' => 'external-video',
                    'label' => 'my_video.mp4',
                    'position' => 0,
                    'disabled' => false,
                    'types' => [],
                    'content' => [
                        'base64_encoded_data' => base64_encode('this is the cover image'),
                        'type' => 'image/jpeg',
                        'name' => 'cover.jpg'
                    ],
                    'extension_attributes' => [
                        'video_content' => [
                            'media_type' => 'external-video',
                            'video_provider' => '',
                            'video_url' => 'https://player.vimeo.com/external/test.sd.mp4',
                            'video_title' => 'SKU-123',
                            'video_description' => '',
                            'video_metadata' => ''
                        ]
                    ]
                ]
            ),
        ];

        wait($this->client->deleteProductMedia('SKU-123', '1'));

        $mediaGalleryEntries = Routes::$products['SKU-123']->media_gallery_entries;
        $this->assertCount(0, $mediaGalleryEntries);
    }

    public function testRequestWithAccessToken()
    {
        $configWithAccessToken = [
            'baseUrl' => 'http://my-url',
            'accessToken' => 'access-token-for-esb-integration',
        ];
        $inMemoryMagento = new Server(realpath(self::MAGENTO_SCHEMA_JSON_FILE), new Routes());
        $fakeClient = new HttpClient($inMemoryMagento);
        $client = new ApiClient($fakeClient, $configWithAccessToken);

        Routes::$accessToken='access-token-for-esb-integration';
        Routes::$products['SKU-123'] = $this->object(
            [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
                'attribute_set_id' => 4,
                'type_id' => 'simple',
                'media_gallery_entries' => []
            ]
        );

        $foundProduct = wait($client->getProduct('SKU-123'));

        $this->assertNotNull($foundProduct);
    }

    private function getStockItemData(array $override): array
    {
        return array_merge(
            [
                'qty' => 0,
                'is_in_stock' => false,
                'is_qty_decimal' => false,
                'show_default_notification_message' => true,
                'use_config_min_qty' => true,
                'min_qty' => 1,
                'use_config_min_sale_qty' => 1,
                'min_sale_qty' => 1,
                'use_config_max_sale_qty' => true,
                'max_sale_qty' => 999999999,
                'use_config_backorders' => true,
                'backorders' => 0,
                'use_config_notify_stock_qty' => true,
                'notify_stock_qty' => 0,
                'use_config_qty_increments' => true,
                'qty_increments' => 1,
                'use_config_enable_qty_inc' => true,
                'enable_qty_increments' => false,
                'use_config_manage_stock' => true,
                'manage_stock' => true,
                'low_stock_date' => '',
                'is_decimal_divided' => false,
                'stock_status_changed_auto' => 0,
            ],
            $override
        );
    }
}
