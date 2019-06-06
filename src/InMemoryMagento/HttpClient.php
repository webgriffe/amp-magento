<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\Client;
use Amp\Artax\HttpException;
use Amp\Artax\Request;
use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;

final class HttpClient implements Client
{
    /**
     * @var Server
     */
    private $magento;

    public function __construct(Server $magento)
    {
        $this->magento = $magento;
    }

    /**
     * @param Request|string $uriOrRequest
     * @param array $options
     * @param CancellationToken|null $cancellation
     * @return Promise
     * @throws HttpException
     * @throws \Throwable
     */
    public function request($uriOrRequest, array $options = [], CancellationToken $cancellation = null): Promise
    {
        return new Success($this->magento->processRequest($uriOrRequest));
    }
}
