<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\ConnectionInfo;
use Amp\Artax\MetaInfo;
use Amp\Artax\Request;
use Amp\Artax\Response as ResponseInterface;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\Message;

class ResponseStub implements ResponseInterface
{
    /** @var int */
    private $status;

    /** @var string|null */
    private $body;

    /** @var array */
    private $headers = [];

    public function __construct(int $status, string $body = null)
    {
        $this->status = $status;
        $this->body = $body;
    }

    /**
     * Retrieve the requests's HTTP protocol version.
     *
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    /**
     * Retrieve the response's three-digit HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase.
     *
     * @return string
     */
    public function getReason(): string
    {
        return (string)$this->status;
    }

    /**
     * Retrieve the Request instance that resulted in this ResponseStub instance.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return new Request('TODO');
    }

    /**
     * Retrieve the original Request instance associated with this ResponseStub instance.
     *
     * A given ResponseStub may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Request that led to this response.
     *
     * @return Request
     */
    public function getOriginalRequest(): Request
    {
        return new Request('TODO');
    }

    /**
     * If this ResponseStub is the result of a redirect traverse up the redirect history.
     *
     * @return ResponseInterface|null
     */
    public function getPreviousResponse()
    {
        return null;
    }

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field Header name.
     *
     * @return bool
     */
    public function hasHeader(string $field): bool
    {
        return false;
    }

    /**
     * Retrieve the first occurrence of the specified header in the message.
     *
     * If multiple headers exist for the specified field only the value of the first header is returned. Applications
     * may use `getHeaderArray()` to retrieve a list of all header values received for a given field.
     *
     * A `null` return indicates the requested header field was not present.
     *
     * @param string $field Header name.
     *
     * @return string|null Header value or `null` if no header with name `$field` exists.
     */
    public function getHeader(string $field)
    {
        if (empty($this->headers[$field])) {
            return null;
        }
        return (string)$this->headers[$field][0];
    }

    /**
     * Retrieve all occurrences of the specified header in the message.
     *
     * Applications may use `getHeader()` to access only the first occurrence.
     *
     * @param string $field Header name.
     *
     * @return array Header values.
     */
    public function getHeaderArray(string $field): array
    {
        return (array)$this->headers[$field];
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values.
     *
     * **Format**
     *
     * ```php
     * [
     *     "header-1" => [
     *         "value-1",
     *         "value-2",
     *     ],
     *     "header-2" => [
     *         "value-1",
     *     ],
     * ]
     * ```
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieve the response body.
     *
     * Note: If you stream a Message, you can't consume the payload twice.
     *
     * @return Message
     */
    public function getBody(): Message
    {
        return new Message(new InMemoryStream($this->body));
    }

    /**
     * @return MetaInfo
     */
    public function getMetaInfo(): MetaInfo
    {
        return new MetaInfo(new ConnectionInfo('TODO', 'TODO'));
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name][] = $value;
        return $this;
    }
}
