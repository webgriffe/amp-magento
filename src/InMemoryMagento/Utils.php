<?php
declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Amp\Artax\HttpException;
use Amp\Artax\Request;
use Amp\Uri\InvalidUriException;
use Amp\Uri\Uri;
use Amp\Promise;

trait Utils
{
    /**
     * @param string $str
     * @return Uri
     * @throws HttpException
     */
    private static function buildUriFromString(string $str): Uri
    {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();

            if (($scheme === 'http' || $scheme === 'https') && $uri->getHost()) {
                return $uri;
            }

            throw new HttpException('Request must specify a valid HTTP URI');
        } catch (InvalidUriException $e) {
            throw new HttpException('Request must specify a valid HTTP URI', 0, $e);
        }
    }

    /**
     * @param array $data
     * @param string $query
     * @return ResponseStub
     */
    private static function createSearchCriteriaResponse(array $data, string $query): ResponseStub
    {
        $parsedQuery = [];
        parse_str($query, $parsedQuery);
        if (!array_key_exists('searchCriteria', $parsedQuery)) {
            throw new \Error('Parameter searchCriteria is required');
        }
        $filter = $parsedQuery['searchCriteria']['filterGroups'][0]['filters'][0] ?? null;
        if (!$filter) {
            return new ResponseStub(
                200,
                json_encode(
                    [
                        'items' => $data,
                        'search_criteria' => [],
                        'total_count' => \count($data)
                    ]
                )
            );
        }
        $data = array_filter($data, function (\stdClass $element) use ($filter) {
            $field = $filter['field'];
            $actualValue = $element->$field ?? null;
            if (null === $actualValue) {
                $customAttributes = $element->custom_attributes;
                if ($customAttributes) {
                    $actualValue = array_reduce($customAttributes, function ($carry, $customAttribute) use ($field) {
                        if ($customAttribute->attribute_code === $field) {
                            return $customAttribute->value;
                        }
                    });
                }
            }

            if (empty($filter['conditionType']) || $filter['conditionType'] === 'eq') {
                return $actualValue === $filter['value'];
            }
            if ($filter['conditionType'] === 'gt') {
                return $actualValue > $filter['value'];
            }
            throw new \Error(sprintf('Condition Type "%s" not supported', $filter['conditionType']));
        });
        return new ResponseStub(
            200,
            json_encode(
                [
                    'items' => array_values($data),
                    'search_criteria' => [],
                    'total_count' => \count($data)
                ]
            )
        );
    }

    /**
     * Unset element from a multi-dimensional array by path like "path/to/value"
     * See: https://stackoverflow.com/questions/48931260/unset-element-in-multi-dimensional-array-by-path
     *
     * @param array $array
     * @param string $path
     * @return bool
     */
    private static function unsetByPath(array &$array, string $path): bool
    {
        $pathArray = explode('/', $path);
        $temp = &$array;

        foreach ($pathArray as $key) {
            if (isset($temp[$key])) {
                if (!($key === end($pathArray))) {
                    $temp = &$temp[$key];
                }
            } else {
                return false; //invalid path
            }
        }
        unset($temp[end($pathArray)]);
        return true;
    }

    /**
     * @param Request $request
     * @return \stdClass
     * @throws \Throwable
     */
    private static function readDecodedRequestBody(Request $request): \stdClass
    {
        return json_decode(Promise\wait($request->getBody()->createBodyStream()->read()), false);
    }

    /**
     * @param array $array
     * @return \stdClass
     */
    private function object(array $array): \stdClass
    {
        return json_decode(json_encode($array));
    }
}
