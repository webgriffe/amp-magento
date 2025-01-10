<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\CancellationToken;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Promise;
use Amp\Success;

final class HttpClient implements DelegateHttpClient
{
    /** @var Server */
    private $magento;

    public function __construct(Server $magento)
    {
        $this->magento = $magento;
    }

    /**
     * @param Request|string $uriOrRequest
     * @param CancellationToken|null $cancellation
     *
     * @throws HttpException
     * @throws \Throwable
     */
    public function request($uriOrRequest, ?CancellationToken $cancellation = null): Promise
    {
        return new Success($this->magento->processRequest($uriOrRequest));
    }
}
