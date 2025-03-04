<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento;

use Amp\Http\Client\DelegateHttpClient as HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\File;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Success;
use Webmozart\Assert\Assert;
use function Amp\call;

final class ApiClient
{
    const DEFAULT_CLIENT_TIMEOUT = 30000;

    /** @var HttpClient */
    private $client;

    /** @var array */
    private $config;

    /** @var string */
    private $token;

    public function __construct(HttpClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @param string $sku
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function getProduct(string $sku): Promise
    {
        return call(function () use ($sku) {
            $request = new Request($this->getAbsoluteUri('/V1/products/' . urlencode($sku)), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            if ($response->getStatus() === 404) {
                return null;
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param array $filters
     * @return Promise
     */
    public function getProducts(array $filters = [], string $storeCode = null): Promise
    {
        // TODO: add a test with store code
        return call(function () use ($filters, $storeCode) {
            $uri = '/V1/products' . $this->buildQueryStringWithSearchCriteria($filters);

            $request = new Request($this->getAbsoluteUri($uri, $storeCode), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param array $productData
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function createProduct(array $productData): Promise
    {
        return call(function () use ($productData) {
            $request = $this->createJsonRequest($this->getAbsoluteUri('/V1/products'), 'POST', $productData);
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $sku
     * @param array $productData
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function updateProduct(string $sku, array $productData, string $storeCode = null): Promise
    {
        return call(function () use ($sku, $productData, $storeCode) {
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri('/V1/products/' . urlencode($sku), $storeCode),
                'PUT',
                $productData
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $sku
     * @param string|null $storeCode
     *
     * @return Promise
     */
    public function getProductMediaGallery(string $sku, string $storeCode = null): Promise
    {
        return call(function () use ($sku, $storeCode) {
            $sku = urlencode($sku);
            $request = new Request(
                $this->getAbsoluteUri(
                    "/V1/products/{$sku}/media",
                    $storeCode
                ),
                'GET'
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);

            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $sku
     * @param array $mediaData
     * @param string|null $storeCode
     *
     * @return Promise
     */
    public function addProductMedia(string $sku, array $mediaData, string $storeCode = null): Promise
    {
        return call(function () use ($sku, $mediaData, $storeCode) {
            $sku = urlencode($sku);
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri("/V1/products/{$sku}/media", $storeCode),
                'POST',
                $mediaData
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $sku
     * @param string $mediaId
     * @param array $mediaData
     * @param string|null $storeCode
     *
     * @return Promise
     */
    public function updateProductMedia(
        string $sku,
        string $mediaId,
        array $mediaData,
        string $storeCode = null
    ): Promise {
        return call(function () use ($sku, $mediaId, $mediaData, $storeCode) {
            $sku = urlencode($sku);
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri("/V1/products/{$sku}/media/{$mediaId}", $storeCode),
                'PUT',
                $mediaData
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $sku
     * @param string $mediaId
     * @return Promise
     */
    public function deleteProductMedia(string $sku, string $mediaId): Promise
    {
        $sku = urlencode($sku);
        return $this->makeDeleteRequest("/V1/products/{$sku}/media/{$mediaId}");
    }

    /**
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function getAllProductAttributes(): Promise
    {
        return call(function () {
            $request = new Request($this->getAbsoluteUri('/V1/products/attributes?searchCriteria=[]'), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $attributeCode
     * @return Promise
     */
    public function getProductAttributeByCode(string $attributeCode): Promise
    {
        return call(function () use ($attributeCode) {
            $uri = '/V1/products/attributes?' .
                "searchCriteria[filterGroups][0][filters][0][field]=attribute_code&".
                "searchCriteria[filterGroups][0][filters][0][value]=$attributeCode";
            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $attributeCode
     * @param string $value
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function getCategoriesByAttribute(string $attributeCode, string $value): Promise
    {
        return call(function () use ($attributeCode, $value) {
            $uri = '/V1/categories/list?' .
                "searchCriteria[filterGroups][0][filters][0][field]=$attributeCode&".
                "searchCriteria[filterGroups][0][filters][0][value]=$value";
            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $attributeCode
     * @param string $label
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function createProductAttributeOption(
        string $attributeCode,
        string $label,
        array $translations = []
    ): Promise {
        return call(function () use ($label, $attributeCode, $translations) {
            $newOption = [
                'option' => [
                    'label' => $label,
                    'value' => $label,  //For text swatch attributes, the swatch value is taken from the value
                ]
            ];
            if (!empty($translations)) {
                $newOption['option']['store_labels'] = $translations;
            }
            $relativeUri = '/V1/products/attributes/' . $attributeCode . '/options';
            $request = $this->createJsonRequest($this->getAbsoluteUri($relativeUri), 'POST', $newOption);
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }


    /**
     * @param string $attributeCode
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function getAllProductAttributeOptions(string $attributeCode, string $storeCode = null): Promise
    {
        return call(function () use ($attributeCode, $storeCode) {
            $randomParam = time() . random_int(0, 1000);
            $uri = '/V1/products/attributes/' . $attributeCode . '/options?p=' . $randomParam;
            $request = new Request($this->getAbsoluteUri($uri, $storeCode), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $childSku
     * @param string $parentSku
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \TypeError
     * @throws \RuntimeException
     */
    public function linkChildProductToConfigurable(string $childSku, string $parentSku): Promise
    {
        return call(function () use ($childSku, $parentSku) {
            $parentSku = urlencode($parentSku);
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri("/V1/configurable-products/{$parentSku}/child"),
                'POST',
                ['childSku' => $childSku]
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            $responseBody = yield $response->getBody()->buffer();
            $error = json_decode($responseBody ?? '', true);
            // TODO Improvement: make a more robust way to handle product already linked to configurable error
            $productAlreadyLinkedMessage = ($error['message'] === 'Product has been already attached') ||
                ($error['message'] === 'Il prodotto è già stato associato') ||
                ($error['message'] === 'The product is already attached.');
            if ($productAlreadyLinkedMessage && $response->getStatus() === 400) {
                return true;
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string|null $createdAfter
     *
     * @return Promise
     */
    public function getInvoices(?string $createdAfter = null, ?int $state = null): Promise
    {
        return call(function () use ($createdAfter, $state) {
            $uri = '/V1/invoices?searchCriteria=[]';
            $filters = [];

            if ($createdAfter) {
                $filters[] = sprintf(
                    'searchCriteria[filterGroups][%1$d][filters][0][field]=created_at' .
                    '&searchCriteria[filterGroups][%1$d][filters][0][value]=%2$s' .
                    '&searchCriteria[filterGroups][%1$d][filters][0][conditionType]=gt',
                    count($filters),
                    urlencode($createdAfter)
                );
            }
            if ($state) {
                $filters[] = sprintf(
                    'searchCriteria[filterGroups][%1$d][filters][0][field]=state' .
                    '&searchCriteria[filterGroups][%1$d][filters][0][value]=%2$s' .
                    '&searchCriteria[filterGroups][%1$d][filters][0][conditionType]=eq',
                    count($filters),
                    $state
                );
            }

            if (count($filters) > 0) {
                $uri = sprintf('/V1/invoices?%s', join('&', $filters));
            }

            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * TODO: Needs to be unified with getInvoices()
     *
     * @param array $filters
     *
     * @return Promise
     *
     * @see getOrders() for how to use $filters
     */
    public function getInvoicesWithFilter(array $filters)
    {
        return call(function () use ($filters) {
            $uri = '/V1/invoices' . $this->buildQueryStringWithSearchCriteria($filters);
            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param array $filters Filters to apply to the search, specified as a multidimensional array. Elements of the
     *                      outermost array are considered to be conditions in AND. Element of the second level are in
     *                      OR. Elements at the last level must contain the 'field', 'value' and 'condition' keys.

     *                      Examples:
     *                      [
     *                          ['field' => 'x', 'condition' => 'eq', 'value' => 'A'],
     *                      ]
     *                      is rendered as
     *                      WHERE x = 'A'
     *
     *                      [
     *                          ['field' => 'x', 'condition' => 'lt', 'value' => 10],
     *                          ['field' => 'y', 'condition' => 'like', 'value' => 'ABC%'],
     *                      ]
     *                      results in:
     *                      WHERE x < 10 AND y LIKE 'ABC%'
     *
     *                      [
     *                          ['field' => 'x', 'condition' => 'in', 'value' => ['A', 'B', 'C']],
     *                          [
     *                              ['field' => 'y', 'condition' => 'null', 'value' => true],
     *                              ['field' => 'y', 'condition' => 'lt', 'value' => 100],
     *                          ]
     *                      ]
     *                      produces:
     *                      WHERE x IN ('A', 'B', 'C') AND (y IS NULL OR y < 100)
     *
     *                      This mirrors the underlying Magento API:
     *                      https://devdocs.magento.com/guides/v2.3/rest/performing-searches.html
     * @return Promise
     */
    public function getOrders(array $filters = []): Promise
    {
        return call(function () use ($filters) {
            $uri = '/V1/orders' . $this->buildQueryStringWithSearchCriteria($filters);

            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param int $orderId
     * @return Promise
     */
    public function getOrder(int $orderId): Promise
    {
        return call(function () use ($orderId) {
            $uri = sprintf('/V1/orders/%s', $orderId);
            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    public function getStockItem(string $sku): Promise
    {
        return call(function () use ($sku) {
            $uri = sprintf('/V1/stockItems/%s', urlencode($sku));
            $request = new Request($this->getAbsoluteUri($uri), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            if ($response->getStatus() === 404) {
                return null;
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    public function updateStockItem(string $sku, array $stockItemData): Promise
    {
        return call(
            function () use ($sku, $stockItemData) {
                $stockItemId = $stockItemData['stockItem']['item_id'];
                $request = $this->createJsonRequest(
                    $this->getAbsoluteUri(
                        sprintf('/V1/products/%s/stockItems/%s', urlencode($sku), $stockItemId)
                    ),
                    'PUT',
                    $stockItemData
                );
                /** @var Response $response */
                $response = yield $this->makeApiRequest($request);
                if ($response->getStatus() === 200) {
                    return json_decode((yield $response->getBody()->buffer()) ?? '', true);
                }

                throw yield $this->unexpectedResponseException($request, $response);
            }
        );
    }

    public function invoiceOrder(int $orderId, array $invoiceData): Promise
    {
        return call(function () use ($orderId, $invoiceData) {
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri(sprintf('/V1/order/%s/invoice', $orderId)),
                'POST',
                $invoiceData
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            if ($response->getStatus() === 404) {
                return false;
            }
            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    public function shipOrder(int $orderId, array $shipmentData): Promise
    {
        //Just an alias with a more generic name
        return call(function () use ($orderId, $shipmentData) {
            yield $this->createShipmentTrack((string)$orderId, $shipmentData);
        });
    }

    public function cancelOrder(int $orderId): Promise
    {
        return call(function () use ($orderId) {
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri(sprintf('/V1/orders/%s/cancel', $orderId)),
                'POST'
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            if ($response->getStatus() === 404) {
                return false;
            }
            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param array $filters
     *
     * @return Promise
     *
     * @see getOrders() for how to use $filters
     */
    public function getShipments(array $filters)
    {
        return call(
            function () use ($filters) {
                $uri = '/V1/shipments'.$this->buildQueryStringWithSearchCriteria($filters);
                $request = new Request($this->getAbsoluteUri($uri), 'GET');
                /** @var Response $response */
                $response = yield $this->makeApiRequest($request);
                if ($response->getStatus() === 200) {
                    return json_decode((yield $response->getBody()->buffer()) ?? '', true);
                }

                throw yield $this->unexpectedResponseException($request, $response);
            }
        );
    }

    public function createShipmentTrack(string $orderId, array $trackingData): Promise
    {
        return call(function () use ($orderId, $trackingData) {
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri(sprintf('/V1/order/%s/ship', $orderId)),
                'POST',
                $trackingData
            );
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            if ($response->getStatus() === 404) {
                return false;
            }
            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $uriPath
     * @return Promise
     */
    public function makeGetRequest(string $uriPath): Promise
    {
        return call(function () use ($uriPath) {
            $request = new Request($this->getAbsoluteUri($uriPath), 'GET');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $uriPath
     * @param array $payload
     * @return Promise
     */
    public function makePostRequest(string $uriPath, array $payload): Promise
    {
        return call(function () use ($uriPath, $payload) {
            $request = $this->createJsonRequest($this->getAbsoluteUri($uriPath), 'POST', $payload);
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $uriPath
     * @param array $payload
     * @param string $storeCode
     * @return Promise
     */
    public function makePutRequest(string $uriPath, array $payload, string $storeCode = null): Promise
    {
        return call(function () use ($uriPath, $payload, $storeCode) {
            $request = $this->createJsonRequest($this->getAbsoluteUri($uriPath, $storeCode), 'PUT', $payload);
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }


    /**
     * @param string $uriPath
     * @return Promise
     */
    public function makeDeleteRequest(string $uriPath): Promise
    {
        return call(function () use ($uriPath) {
            $request = new Request($this->getAbsoluteUri($uriPath), 'DELETE');
            /** @var Response $response */
            $response = yield $this->makeApiRequest($request);
            if ($response->getStatus() === 200) {
                return json_decode((yield $response->getBody()->buffer()) ?? '', true);
            }
            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     * @throws \RuntimeException
     * @throws \TypeError
     */
    private function login(): Promise
    {
        if (!empty($this->config['accessToken'])) {
            $this->token = $this->config['accessToken'];
            return new Success();
        }
        return $this->loginWithAdminUser();
    }

    private function loginWithAdminUser()
    {
        return call(function () {
            $request = $this->createJsonRequest(
                $this->getAbsoluteUri('/V1/integration/admin/token'),
                'POST',
                ['username' => $this->config['username'], 'password' => $this->config['password']]
            );
            /** @var Response $response */
            $response = yield $this->client->request($request, new NullCancellationToken());
            if ($response->getStatus() === 200) {
                $this->token = json_decode((yield $response->getBody()->buffer()) ?? '', true);
                return;
            }

            if ($response->getStatus() === 401) {
                throw new \RuntimeException('Cannot login as Magento admin. Invalid credentials.');
            }

            throw yield $this->unexpectedResponseException($request, $response);
        });
    }

    /**
     * @param string $relativeUri
     * @return string
     */
    private function getAbsoluteUri(string $relativeUri, string $storeCode = null): string
    {
        $storeCode = $storeCode ?? 'all';
        return rtrim($this->config['baseUrl']) . '/rest/' . $storeCode . $relativeUri;
    }

    /**
     * @param Request $request
     * @return Promise
     * @throws \TypeError
     * @throws \RuntimeException
     */
    private function makeApiRequest(Request $request): Promise
    {
        return call(function () use ($request) {
            $justLoggedIn = false;
            if (!$this->token) {
                yield $this->login();
                $justLoggedIn = true;
            }
            $request->setHeader('Authorization', 'Bearer ' . $this->token);
            $request->setTransferTimeout($this->config['clientTimeout'] ?? self::DEFAULT_CLIENT_TIMEOUT);
            $request->setInactivityTimeout($this->config['clientTimeout'] ?? self::DEFAULT_CLIENT_TIMEOUT);
            /** @var Response $response */
            $response = yield $this->client->request($request, new NullCancellationToken());
            // TODO: Improvement: re-login attempt should be unit tested
            if (!$justLoggedIn && $response->getStatus() === 401) {
                yield $this->login();
                $request->setHeader('Authorization', 'Bearer ' . $this->token);
                $request->setTransferTimeout($this->config['clientTimeout'] ?? self::DEFAULT_CLIENT_TIMEOUT);
                $request->setInactivityTimeout($this->config['clientTimeout'] ?? self::DEFAULT_CLIENT_TIMEOUT);
                $response = yield $this->client->request($request, new NullCancellationToken());
            }
            return $response;
        });
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Promise
     * @throws \Amp\ByteStream\PendingReadError
     */
    private function unexpectedResponseException(Request $request, Response $response): Promise
    {
        return call(
            function () use ($request, $response) {
                $responseStatus = $response->getStatus();
                $requestBody = yield $request->getBody()->createBodyStream()->read();
                $responseBody = yield $response->getBody()->buffer();
                $responseDecoded = json_decode($responseBody ?? '', true);
                $responseMessage = '<undefined>';
                if (\is_array($responseDecoded) && array_key_exists('message', $responseDecoded)) {
                    $responseMessage = $responseDecoded['message'];
                }
                $id = bin2hex(random_bytes(4));
                $requestBodyFile = rtrim(sys_get_temp_dir(), '/') . '/' . date('Ymd') . '-' . $id . '-request.log';
                $responseBodyFile = rtrim(sys_get_temp_dir(), '/') . '/' . date('Ymd') . '-' . $id . '-response.log';
                yield File\write($requestBodyFile, $requestBody ?? '');
                yield File\write($responseBodyFile, $responseBody ?? '');
                return new \RuntimeException(
                    sprintf(
                        'Unexpected response status (%s) with error message "%s" received from Magento API request ' .
                        '"%s". Full request and response bodies were dumped to files "%s" and "%s".',
                        $responseStatus,
                        $responseMessage,
                        "{$request->getMethod()} {$request->getUri()}",
                        $requestBodyFile,
                        $responseBodyFile
                    ),
                    $responseStatus
                );
            }
        );
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array $data
     * @return Request
     */
    private function createJsonRequest(string $uri, string $method, array $data = null): Request
    {
        // TODO: Maybe it could be removed: both product payload and products description in file are converted to utf-8
        $request = new Request($uri, $method);
        $request->setHeader('Content-Type', 'application/json');
        if ($data) {
            array_walk_recursive($data, [$this, 'detectAndCleanUtf8']);
            $request->setBody(json_encode($data));
        }
        return $request;
    }

    /**
     * @param mixed $data
     */
    private function detectAndCleanUtf8(&$data): void
    {
        if (is_string($data) && !preg_match('//u', $data)) {
            $data = preg_replace_callback(
                '/[\x80-\xFF]+/',
                function ($m) {
                    return mb_convert_encoding($m[0], 'UTF-8', 'ISO-8859-1');
                },
                $data
            );
            Assert::string($data);
            $data = str_replace(
                array('¤', '¦', '¨', '´', '¸', '¼', '½', '¾'),
                array('€', 'Š', 'š', 'Ž', 'ž', 'Œ', 'œ', 'Ÿ'),
                $data
            );
        }
    }

    /**
     * @param array $filters
     * @return string
     */
    private function buildQueryStringWithSearchCriteria(array $filters): string
    {
        $queryString = '';
        if (count($filters) == 0) {
            $queryString .= '?searchCriteria=[]';
        } else {
            $groupIndex = 0;
            foreach ($filters as $filterGroup) {
                if (array_key_exists('field', $filterGroup) &&
                    array_key_exists('value', $filterGroup) &&
                    array_key_exists('condition', $filterGroup)) {
                    //Single element group. Make it a single element array for uniformity
                    $filterGroup = [$filterGroup];
                }

                $filterIndex = 0;
                $filterGroupUrls = [];
                foreach ($filterGroup as $filter) {
                    $value = $filter['value'];
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    $filterGroupUrls[] = sprintf(
                        "searchCriteria[filterGroups][{$groupIndex}][filters][{$filterIndex}][field]=%s" .
                        "&searchCriteria[filterGroups][{$groupIndex}][filters][{$filterIndex}][value]=%s" .
                        "&searchCriteria[filterGroups][{$groupIndex}][filters][{$filterIndex}][conditionType]=%s",
                        urlencode($filter['field']),
                        urlencode($value),
                        urlencode($filter['condition'])
                    );
                    ++$filterIndex;
                }
                $filterGroupUrl = implode('&', $filterGroupUrls);

                if ($groupIndex == 0) {
                    $queryString .= '?'.$filterGroupUrl;
                } else {
                    $queryString .= '&'.$filterGroupUrl;
                }

                ++$groupIndex;
            }
        }
        return $queryString;
    }
}
