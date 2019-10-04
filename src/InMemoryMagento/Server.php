<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\HttpException;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Promise;
use FastRoute;
use FastRoute\Dispatcher\GroupCountBased;
use FR3D\SwaggerAssertions\PhpUnit\AssertsTrait;
use FR3D\SwaggerAssertions\SchemaManager;
use PHPUnit\Framework\ExpectationFailedException;

final class Server
{
    use AssertsTrait;
    use Utils;

    /**
     * @var array
     */
    private $schema;

    /**
     * @var string
     */
    private $mageVersion;

    /**
     * @var FastRoute\Dispatcher
     */
    private $dispatcher;

    /**
     * Server constructor.
     *
     * @param string|array $swaggerSchemaParams
     * @param Routes       $inMemoryMagentoRoutes
     */
    public function __construct($swaggerSchemaParams, Routes $inMemoryMagentoRoutes)
    {
        if (!is_array($swaggerSchemaParams)) {
            $swaggerSchemaParams = ['all' => $swaggerSchemaParams];
        }

        array_walk(
            $swaggerSchemaParams,
            function ($schema, $code) {
                $swaggerSchema = json_decode($schema, false);
                $this->schema[$code] = new SchemaManager($swaggerSchema);
            }
        );

        $this->mageVersion = $this->getMageVersionFromAnyOfTheSchemas($swaggerSchemaParams);
        $this->dispatcher  = new GroupCountBased($inMemoryMagentoRoutes->getData());
    }

    /**
     * @param string|Request $uriOrRequest
     *
     * @return Response
     * @throws HttpException
     * @throws \Throwable
     */
    public function processRequest($uriOrRequest): Response
    {
        $request = $this->normalizeRequest($uriOrRequest);

        try {
            $this->validateRequestAgainstSchema($request);
        } catch (ExpectationFailedException $error) {
            $response = new ResponseStub(
                400,
                json_encode(
                    [
                        'message' => $error->getMessage(),
                        'code'    => $error->getCode(),
                        'trace'   => $error->getTraceAsString()
                    ]
                )
            );

            return $response;
        }

        $response = $this->doProcessRequest($request);

        $this->validateResponseAgainstSchema($request, $response);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws HttpException
     * @throws \Throwable
     */
    private function doProcessRequest(Request $request): Response
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            self::buildUriFromString($request->getUri())->getPath()
        );
        if ($routeInfo[0] === FastRoute\Dispatcher::NOT_FOUND) {
            throw new \Error(
                sprintf(
                    'This fake has no idea how to handle the request "%s %s".',
                    $request->getMethod(),
                    self::buildUriFromString($request->getUri())->getPath()
                )
            );
        }
        if ($routeInfo[0] === FastRoute\Dispatcher::METHOD_NOT_ALLOWED) {
            $allowedMethods = $routeInfo[1];
            throw new \Error(
                sprintf(
                    'Method "%s" is not allowed for URI "%s", allowed methods are "%s".',
                    $request->getMethod(),
                    self::buildUriFromString($request->getUri())->getPath(),
                    implode(', ', $allowedMethods)
                )
            );
        }
        if ($routeInfo[0] !== FastRoute\Dispatcher::FOUND) {
            throw new \Error(sprintf('Unexpected route info "%s".', $routeInfo[0]));
        }
        $handler = $routeInfo[1];
        $vars    = $routeInfo[2];

        return $handler($request, $vars, $this->mageVersion);
    }

    /**
     * @param Request|string $uriOrRequest
     *
     * @return Request
     * @throws HttpException
     */
    private function normalizeRequest($uriOrRequest): Request
    {
        if ($uriOrRequest instanceof Request) {
            return $uriOrRequest;
        }
        if (is_string($uriOrRequest)) {
            return new Request((string)self::buildUriFromString($uriOrRequest));
        }

        throw new HttpException(
            'Request must be a valid HTTP URI or Amp\Artax\Request instance'
        );
    }

    /**
     * @param Request $request
     *
     * @throws \Throwable
     */
    private function validateRequestAgainstSchema(Request $request): void
    {
        $uri = self::buildUriFromString($request->getUri());

        // check if we have in our schema /rest/[store-code]/V1
        // @TODO: maybe we need a better solution
        $storeCode = explode("/", $uri->getPath());
        $storeCode = $storeCode[2];

        if (!isset($this->schema[$storeCode])) {
            return;
        }

        $this->assertRequestHeadersMatch(
            $request->getHeaders(),
            $this->schema[$storeCode],
            $uri->getPath(),
            $request->getMethod()
        );
        $this->assertRequestQueryMatch(
            $uri->getAllQueryParameters(),
            $this->schema[$storeCode],
            $uri->getPath(),
            $request->getMethod()
        );
        if (in_array(strtoupper($request->getMethod()), ['PUT', 'POST'])) {
            $this->assertRequestBodyMatch(
                self::readDecodedRequestBody($request),
                $this->schema[$storeCode],
                $uri->getPath(),
                $request->getMethod()
            );
        }
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @throws \Throwable
     */
    private function validateResponseAgainstSchema(Request $request, Response $response): void
    {
        $uri = self::buildUriFromString($request->getUri());

        // check if we have in our schema /rest/[store-code]/V1
        // @TODO: maybe we need a better solution
        $storeCode = explode("/", $uri->getPath());
        $storeCode = $storeCode[2];

        if (!isset($this->schema[$storeCode])) {
            return;
        }

        $responseStatus = $response->getStatus();
        $responseBody   = Promise\wait($response->getBody()->read());
        $this->assertResponseHeadersMatch(
            $response->getHeaders(),
            $this->schema[$storeCode],
            $uri->getPath(),
            $request->getMethod(),
            $responseStatus
        );
        $this->assertResponseBodyMatch(
            json_decode($responseBody, false),
            $this->schema[$storeCode],
            $uri->getPath(),
            $request->getMethod(),
            $responseStatus
        );
    }

    /**
     * @param array $swaggerSchemaParams
     * @return string
     */
    private function getMageVersionFromAnyOfTheSchemas($swaggerSchemaParams)
    {
        $anySwaggerSchema = json_decode(array_values($swaggerSchemaParams)[0], false);
        return $anySwaggerSchema->info->version;
    }
}
