<?php

declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\HttpException;
use Amp\Artax\Request;
use FastRoute\DataGenerator;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use Webmozart\Assert\Assert;

class Routes extends RouteCollector
{
    use Utils;

    const ADMIN_USER = 'admin';
    const ADMIN_PASS = 'password123';
    const DEFAULT_VISIBILITY_CATALOG_SEARCH = 4;

    /**
     * attribute_code (string): Code of the attribute. ,
     * frontend_input (string): HTML for input element. ,
     * entity_type_id (string): Entity type id ,
     * is_required (boolean): Attribute is required. ,
     * frontend_labels (Array[eav-data-attribute-frontend-label-interface]): Frontend label for each store ,
     *
     * @var array
     */
    public static $invoices = [];
    public static $stockItems = [];
    public static $productAttributes = [];
    public static $shipmentTracks = [];
    public static $orders = [];
    public static $categories = [];
    public static $products = [];

    public function __construct()
    {
        parent::__construct(new RouteParser\Std(), new DataGenerator\GroupCountBased());

        self::$productAttributes = [];
        self::$categories = [];
        self::$products = [];
        self::$invoices = [];
        self::$orders = [];
        self::$stockItems = [];
        self::$shipmentTracks = [];

        $this->addRoute(
            'POST',
            '/rest/all/V1/integration/admin/token',
            [__CLASS__, 'postIntegrationAdminTokenHandler']
        );
        $this->addRoute('GET', '/rest/all/V1/products/attributes', [__CLASS__, 'getProductsAttributesHandler']);
        $this->addRoute('GET', '/rest/all/V1/categories/list', [__CLASS__, 'getCategoriesListHandler']);
        $this->addRoute('GET', '/rest/all/V1/products/{sku}', [__CLASS__, 'getProductsHandler']);
        $this->addRoute('POST', '/rest/all/V1/products', [__CLASS__, 'postProductsHandler']);
        $this->addRoute('PUT', '/rest/all/V1/products/{sku}', [__CLASS__, 'putProductsHandler']);
        $this->addRoute('PUT', '/rest/{storeCode}/V1/products/{sku}', [__CLASS__, 'putProductsForStoreViewHandler']);
        $this->addRoute(
            'POST',
            '/rest/all/V1/products/attributes/{attributeCode}/options',
            [__CLASS__, 'postProductsAttributesOptionsHandler']
        );
        $this->addRoute(
            'GET',
            '/rest/all/V1/products/attributes/{attributeCode}/options',
            [__CLASS__, 'getProductAttributesOptionsHandler']
        );
        $this->addRoute(
            'GET',
            '/rest/{storeCode}/V1/products/attributes/{attributeCode}/options',
            [__CLASS__, 'getProductAttributesOptionsForStoreViewHandler']
        );
        $this->addRoute(
            'POST',
            '/rest/all/V1/configurable-products/{parentSku}/child',
            [__CLASS__, 'postConfigurableProductsChildHandler']
        );
        $this->addRoute('GET', '/rest/all/V1/invoices', [__CLASS__, 'getInvoicesHandler']);
        $this->addRoute('GET', '/rest/all/V1/orders/{orderId}', [__CLASS__, 'getOrderHandler']);
        $this->addRoute('GET', '/rest/all/V1/orders', [__CLASS__, 'getOrdersHandler']);
        $this->addRoute('GET', '/rest/all/V1/stockItems/{sku}', [__CLASS__, 'getStockItemsHandler']);
        $this->addRoute(
            'PUT',
            '/rest/all/V1/products/{sku}/stockItems/{stockItemId}',
            [__CLASS__, 'putProductsStockItemsHandler']
        );
        $this->addRoute('POST', '/rest/all/V1/order/{orderId}/ship', [__CLASS__, 'postOrderShipHandler']);
    }

    /**
     * @param Request $request
     * @param array   $uriParams
     *
     * @return ResponseStub
     * @throws \Throwable
     *
     * BEFORE
     * $product =
     * [
     *   'sku' => 'sku-123',
     *   'price'   => 10,
     *   '_stores' => [
     *       'it_it' => [
     *           'price' => 20
     *       ]
     *   ]
     * ]
     *
     * AFTER
     * $product =
     * [
     *   'sku' => 'sku-123',
     *   'price'   => 50,    <- but is referred to it_it storeCode
     *   '_stores' => [
     *       'it_it' => [
     *           'price' => 20
     *       ]
     *   ]
     * ]
     *
     * Api call of magento doesn't have '_stores' value
     */
    public static function putProductsForStoreViewHandler(Request $request, array $uriParams): ResponseStub
    {
        $sku     = $uriParams['sku'];
        $product = self::readDecodedRequestBody($request)->product;

        // Product data updated can be found on "object" root
        // We have to take that data
        unset($product->_stores);
        if (empty(self::$products[$sku]->_stores)) {
            self::$products[$sku]->_stores = new \stdClass();
        }
        self::$products[$sku]->_stores->{$uriParams['storeCode']} = $product;

        $response = new ResponseStub(200, json_encode(self::$products[$sku]));

        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     *
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function postIntegrationAdminTokenHandler(Request $request, array $uriParams): ResponseStub
    {
        $response = new ResponseStub(401, json_encode(['message' => 'Login failed']));
        if (self::readDecodedRequestBody($request)->username === self::ADMIN_USER &&
            self::readDecodedRequestBody($request)->password === self::ADMIN_PASS) {
            $response = new ResponseStub(200, json_encode(uniqid('', true)));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws HttpException
     */
    public static function getProductsAttributesHandler(Request $request, array $uriParams): ResponseStub
    {
        return self::createSearchCriteriaResponse(
            self::$productAttributes,
            self::buildUriFromString($request->getUri())->getQuery()
        );
    }

    public static function getProductAttributesOptionsForStoreViewHandler(
        Request $request,
        array $uriParams
    ): ResponseStub {
        // TODO: To be implemented
        return self::getProductAttributesOptionsHandler($request, $uriParams);
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws HttpException
     */
    public static function getCategoriesListHandler(Request $request, array $uriParams): ResponseStub
    {
        return self::createSearchCriteriaResponse(
            self::$categories,
            self::buildUriFromString($request->getUri())->getQuery()
        );
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     */
    public static function getProductsHandler(Request $request, array $uriParams): ResponseStub
    {
        $sku = $uriParams['sku'];
        $response = new ResponseStub(404, json_encode(['message' => 'Product not found.']));
        if (isset(self::$products[$sku])) {
            $response = new ResponseStub(200, json_encode(self::$products[$sku]));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function postProductsHandler(Request $request, array $uriParams): ResponseStub
    {
        $product = self::readDecodedRequestBody($request)->product;
        Assert::isInstanceOf($product, \stdClass::class);
        if (!isset($product->price)) {
            $response = new ResponseStub(
                400,
                json_encode(['message' => 'The value of attribute "price" must be set.'])
            );
            return $response;
        }
        if (isset($product->weight) && !\is_numeric($product->weight)) {
            $response = new ResponseStub(
                400,
                json_encode(['message' => '"Error occurred during "weight" processing. Invalid type.'])
            );
            return $response;
        }
        if (!isset($product->visibility)) {
            $product->visibility = self::DEFAULT_VISIBILITY_CATALOG_SEARCH;
        }
        if (!empty($product->extension_attributes->configurable_product_options)) {
            foreach ($product->extension_attributes->configurable_product_options as $configurableProductOption) {
                if (empty($configurableProductOption->values)) {
                    $response = new ResponseStub(
                        400,
                        json_encode(['message' => 'Option values are not specified.'])
                    );
                    return $response;
                }
            }
        }
        $product->id = (string)random_int(1000, 10000);
        self::$products[$product->sku] = $product;
        $response = new ResponseStub(200, json_encode($product));
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function putProductsHandler(Request $request, array $uriParams): ResponseStub
    {
        $sku = $uriParams['sku'];
        $product = self::readDecodedRequestBody($request)->product;
        if (isset($product->weight) && !\is_numeric($product->weight)) {
            $response = new ResponseStub(
                400,
                json_encode(['message' => '"Error occurred during "weight" processing. Invalid type.'])
            );
            return $response;
        }
        if (!isset($product->visibility)) {
            $product->visibility = self::DEFAULT_VISIBILITY_CATALOG_SEARCH;
        }
        if (!empty($product->extension_attributes->configurable_product_options)) {
            foreach ($product->extension_attributes->configurable_product_options as $configurableProductOption) {
                if (empty($configurableProductOption->values)) {
                    $response = new ResponseStub(
                        400,
                        json_encode(['message' => 'Option values are not specified.'])
                    );
                    return $response;
                }
            }
        }
        self::$products[$sku] = ObjectMerger::merge(self::$products[$sku], $product);
        $response = new ResponseStub(200, json_encode(self::$products[$sku]));
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function postProductsAttributesOptionsHandler(
        Request $request,
        array $uriParams,
        string $mageVersion
    ): ResponseStub {
        $attributeCode = $uriParams['attributeCode'];
        $response = new ResponseStub(404, json_encode(['message' => 'Attribute not found.']));
        if (!empty(self::$productAttributes[$attributeCode])) {
            $option = self::readDecodedRequestBody($request)->option;
            $option->value = (string)random_int(1000, 10000);
            self::$productAttributes[$attributeCode]->options[] = $option;
            $responseBody = true;
            if ($mageVersion === '2.3') {
                $responseBody = sprintf('id_%s', $option->value);
            }
            $response = new ResponseStub(200, json_encode($responseBody));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     */
    public static function getProductAttributesOptionsHandler(Request $request, array $uriParams): ResponseStub
    {
        $attributeCode = $uriParams['attributeCode'];
        $response = new ResponseStub(404, json_encode(['message' => 'Attribute not found.']));
        if (!empty(self::$productAttributes[$attributeCode])) {
            $response = new ResponseStub(200, json_encode(self::$productAttributes[$attributeCode]->options));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function postConfigurableProductsChildHandler(Request $request, array $uriParams): ResponseStub
    {
        $parentSku = $uriParams['parentSku'];
        if (!array_key_exists($parentSku, self::$products) ||
            !array_key_exists(self::readDecodedRequestBody($request)->childSku, self::$products)) {
            return new ResponseStub(404, json_encode(['message' => 'Requested product doesn\'t exist']));
        }
        $childId = self::$products[self::readDecodedRequestBody($request)->childSku]->id;
        $parent = self::$products[$parentSku];
        $configurableProductLinks = $parent->extension_attributes->configurable_product_links ?? null;
        if (!empty($configurableProductLinks) && \in_array($childId, $configurableProductLinks, true)) {
            return new ResponseStub(400, json_encode(['message' => 'Il prodotto è già stato associato']));
        }
        $parent->extension_attributes->configurable_product_links[] = $childId;
        return new ResponseStub(200, json_encode(true));
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws HttpException
     */
    public static function getInvoicesHandler(Request $request, array $uriParams): ResponseStub
    {
        return self::createSearchCriteriaResponse(
            self::$invoices,
            self::buildUriFromString($request->getUri())->getQuery()
        );
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     */
    public static function getOrderHandler(Request $request, array $uriParams): ResponseStub
    {
        $orderId = $uriParams['orderId'];
        $response = new ResponseStub(404, json_encode(['message' => 'Order not found.']));
        if (isset(self::$orders[$orderId])) {
            $response = new ResponseStub(200, json_encode(self::$orders[$orderId]));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws HttpException
     */
    public static function getOrdersHandler(Request $request, array $uriParams): ResponseStub
    {
        return self::createSearchCriteriaResponse(
            self::$orders,
            self::buildUriFromString($request->getUri())->getQuery()
        );
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     */
    public static function getStockItemsHandler(Request $request, array $uriParams): ResponseStub
    {
        $sku = $uriParams['sku'];
        $response = new ResponseStub(404, json_encode(['message' => 'Stock item not found.']));
        if (isset(self::$stockItems[$sku])) {
            $response = new ResponseStub(200, json_encode(self::$stockItems[$sku]));
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function putProductsStockItemsHandler(Request $request, array $uriParams): ResponseStub
    {
        $sku = $uriParams['sku'];
        if (isset(self::readDecodedRequestBody($request)->stockItem->qty) &&
            !\is_numeric(self::readDecodedRequestBody($request)->stockItem->qty)) {
            $response = new ResponseStub(
                400,
                json_encode(['message' => '"Error occurred during "qty" processing. Invalid type.'])
            );
            return $response;
        }
        $stockItem = self::readDecodedRequestBody($request)->stockItem;
        self::$stockItems[$sku] = ObjectMerger::merge(self::$stockItems[$sku], $stockItem);
        $response = new ResponseStub(200, json_encode(self::$stockItems[$sku]->item_id));
        return $response;
    }

    /**
     * @param Request $request
     * @param array $uriParams
     * @return ResponseStub
     * @throws \Throwable
     */
    public static function postOrderShipHandler(Request $request, array $uriParams): ResponseStub
    {
        $orderId = $uriParams['orderId'];
        $response = new ResponseStub(404, json_encode(['message' => 'Order with the given ID does not exist.']));

        if (array_key_exists($orderId, self::$orders)) {
            $orderItemsNumber = array_reduce(
                self::$orders[$orderId]->items,
                function ($counter, $item) {
                    $counter += $item->qty_ordered;
                    return $counter;
                },
                0
            );

            $shippedItemsNumber = array_reduce(
                self::readDecodedRequestBody($request)->items,
                function ($counter, $item) {
                    $counter += $item->qty;
                    return $counter;
                },
                0
            );

            if ($orderItemsNumber === $shippedItemsNumber) {
                self::$orders[$orderId]->status = 'complete';
            }
            $trackId = count(self::$shipmentTracks) + 1;
            $newShipmentTrack = new \stdClass();
            $newShipmentTrack->order_id = $orderId;
            $newShipmentTrack->track_number = null;
            $newShipmentTrack->comment = null;
            if (!empty(self::readDecodedRequestBody($request)->tracks)) {
                $newShipmentTrack->track_number = self::readDecodedRequestBody($request)->tracks[0]->track_number;
            }
            if (!empty(self::readDecodedRequestBody($request)->comment)) {
                $newShipmentTrack->comment = self::readDecodedRequestBody($request)->comment->comment;
            }
            self::$shipmentTracks[$trackId] = $newShipmentTrack;
            $response = new ResponseStub(200, json_encode($trackId));
        }
        return $response;
    }
}
