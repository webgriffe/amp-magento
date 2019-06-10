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

    public function testShouldCreateShipmentTrackForACompleteShipment()
    {
        $this->assertCount(0, Routes::$shipmentTracks);

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

        $this->assertCount(1, Routes::$shipmentTracks);
        $this->assertEquals(1, $trackId);
        $this->assertEquals('0000123', Routes::$shipmentTracks[1]->order_id);
        $this->assertEquals('My comment', Routes::$shipmentTracks[1]->comment);
        $this->assertEquals('TRACK-123', Routes::$shipmentTracks[1]->track_number);
    }

    public function testCreateShipmentTrackShouldCompleteOrderIfItsACompleteShipment()
    {
        $this->assertCount(0, Routes::$shipmentTracks);

        Routes::$orders = [
            '0000123' => $this->object(
                [
                    'status' => 'processing',
                    'items' => [['qty_ordered' => 1]]
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

        $this->assertEquals('complete', Routes::$orders['0000123']->status);
    }

    public function testCreateShipmentTrackShouldNotCompleteOrderIfItsAPartialShipment()
    {
        $this->assertCount(0, Routes::$shipmentTracks);

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
