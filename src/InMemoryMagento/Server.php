<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\HttpException;
use Amp\Artax\Request;
use Amp\Artax\Response;
use FastRoute;
use FastRoute\Dispatcher\GroupCountBased;

final class Server
{
    use Utils;

    /** @var array */
    private $schema;

    /** @var string */
    private $mageVersion;

    /** @var FastRoute\Dispatcher */
    private $dispatcher;

    /**
     * Server constructor.
     *
     * @param Routes       $inMemoryMagentoRoutes
     */
    public function __construct(Routes $inMemoryMagentoRoutes)
    {
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
        return $this->doProcessRequest($this->normalizeRequest($uriOrRequest));
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

        return $handler($request, $vars);
    }

    /**
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
}
