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
     * @param string|null $storeCode
     *
     * @return ResponseStub
     */
    private static function createSearchCriteriaResponse(
        array $data,
        string $query,
        string $storeCode = null
    ): ResponseStub {
        $parsedQuery = [];
        parse_str($query, $parsedQuery);
        if (!array_key_exists('searchCriteria', $parsedQuery)) {
            throw new \Error('Parameter searchCriteria is required');
        }

        if ($storeCode) {
            //When requesting data for a specific store view, replace the generic values with the specific values for
            //that store
            foreach ($data as $index => $element) {
                if (isset($element->_stores->{$storeCode})) {
                    $storeValues = $element->_stores->{$storeCode};

                    //We don't want to modify the original object, so clone it and ensure to do a deep copy of all its
                    //components too
                    $newElement = clone $element;
                    unset($newElement->_stores);

                    foreach ($storeValues as $field => $value) {
                        if ($field == 'custom_attributes' || $field == 'extension_attributes') {
                            //Merge the lists of attributes, giving precedence to the values that are specific to the
                            //store view
                            if (!isset($newElement->{$field})) {
                                $newElement->{$field} = [];
                            }

                            foreach ($storeValues->{$field} as $storeAttributeData) {
                                $newAttribute = new \stdClass();
                                $newAttribute->attribute_code = $storeAttributeData->attribute_code;
                                $newAttribute->value = $storeAttributeData->value;

                                foreach ($newElement->{$field} as $attributeIndex => $genericAttributeData) {
                                    if ($genericAttributeData->attribute_code == $storeAttributeData->attribute_code) {
                                        //Replace the existing item
                                        $newElement->{$field}[$attributeIndex] = $newAttribute;
                                        continue 2;
                                    }
                                }

                                //Add a new item
                                $newElement->{$field}[] = $newAttribute;
                            }
                        } else {
                            //Simple value. Just replace it
                            $newElement->{$field} = $value;
                        }
                    }

                    $data[$index] = $newElement;
                }
            }
        }

        if (empty($parsedQuery['searchCriteria']) || empty($parsedQuery['searchCriteria']['filterGroups'])) {
            $responseSearchCriteria = new \stdClass();
            $responseSearchCriteria->filter_groups = [];
            return new ResponseStub(
                200,
                json_encode(
                    [
                        'items' => array_values($data),
                        'search_criteria' => $responseSearchCriteria,
                        'total_count' => \count($data)
                    ]
                )
            );
        }

        foreach ($parsedQuery['searchCriteria']['filterGroups'] as $filterGroup) {
            $groupData = [];
            foreach ($filterGroup['filters'] as $filter) {
                //Filters are in OR between each other
                $singleFilterData = array_filter($data, function (\stdClass $element) use ($filter) {
                    $field = $filter['field'];
                    $actualValue = $element->{$field} ?? null;

                    if (null === $actualValue) {
                        if (property_exists($element, 'extension_attributes')) {
                            $extensionAttributes = $element->extension_attributes;
                            if (property_exists($extensionAttributes, $field)) {
                                $actualValue = $extensionAttributes->{$field};
                            }
                        }

                        if (property_exists($element, 'custom_attributes') && $element->custom_attributes) {
                            $customAttributes = $element->custom_attributes;
                            $actualValue = array_reduce(
                                $customAttributes,
                                function ($carry, $customAttribute) use ($field) {
                                    if ($customAttribute->attribute_code === $field) {
                                        return $customAttribute->value;
                                    }
                                    return $carry;
                                }
                            );
                        }
                    }

                    if (empty($filter['conditionType'])) {
                        $filter['conditionType'] = 'eq';
                    }

                    switch ($filter['conditionType']) {
                        case 'eq':
                            return $actualValue == $filter['value'];
                        case 'neq':
                            return $actualValue != $filter['value'];
                        case 'gt':
                            return $actualValue > $filter['value'];
                        case 'gteq':
                            return $actualValue >= $filter['value'];
                        case 'lt':
                            return $actualValue < $filter['value'];
                        case 'lteq':
                            return $actualValue <= $filter['value'];
                        case 'in':
                            return in_array($actualValue, explode(',', $filter['value']));
                        case 'nin':
                            return !in_array($actualValue, explode(',', $filter['value']));
                        case 'null':
                            return is_null($actualValue);
                        case 'notnull':
                            return !is_null($actualValue);
                        case 'like':
                            //Implement this using a regex. Regex-quote the value and replace all % chars with .*
                            $regexQuotedValue = preg_quote($filter['value'], '/');
                            $regexQuotedValue = str_replace('%', '.*', $regexQuotedValue);

                            //The like operation is case insensitive, so the regex must be as well
                            return !is_null($actualValue) && preg_match("/{$regexQuotedValue}/i", $actualValue);
                        default:
                            throw new \Error(sprintf('Condition Type "%s" not supported', $filter['conditionType']));
                    }
                });

                //+ operator preserves numeric keys, so duplicate values should not happen
                $groupData += $singleFilterData;
            }

            $data = $groupData;
        }

        $responseSearchCriteria = $parsedQuery['searchCriteria'];
        $responseSearchCriteria['filter_groups'] = $responseSearchCriteria['filterGroups'];
        unset($responseSearchCriteria['filterGroups']);

        return new ResponseStub(
            200,
            json_encode(
                [
                    'items' => array_values($data),
                    'search_criteria' => $responseSearchCriteria,
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
        $requestBody = Promise\wait($request->getBody()->createBodyStream()->read());
        if ($requestBody) {
            return json_decode($requestBody, false);
        } else {
            return new \stdClass();
        }
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
