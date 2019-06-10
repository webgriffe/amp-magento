<?php

namespace Webgriffe\AmpMagento\Tests;

use PHPUnit\Framework\TestCase;
use Webgriffe\AmpMagento\ApiClient;
use Webgriffe\AmpMagento\InMemoryMagento\HttpClient;
use Webgriffe\AmpMagento\InMemoryMagento\Routes;
use Webgriffe\AmpMagento\InMemoryMagento\Server;
use function Amp\Promise\wait;
use Webgriffe\AmpMagento\InMemoryMagento\Utils;

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

    public function testShouldThrowErroCreatingProductWithoutMandatoryData()
    {
        $this->assertCount(0, Routes::$products);

        $productData = [
            'product' => [
                'sku' => 'SKU-123',
                'name' => 'Product Name',
            ]
        ];

        try {
            wait($this->client->createProduct($productData));
        } catch (\RuntimeException $exception) {
            $this->assertCount(0, Routes::$products);
            $this->assertContains('Unexpected response status', $exception->getMessage());
            $this->assertContains('The value of attribute "price" must be set.', $exception->getMessage());
        }
    }
}
