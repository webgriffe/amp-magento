<?php

declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

use Webmozart\Assert\Assert;

final class ObjectMerger
{
    /**
     * Merges two objects into one by replacing values from $object2 into $object1
     *
     * @param \stdClass $product1
     * @param \stdClass $product2
     * @return \stdClass
     */
    public static function merge(\stdClass $product1, \stdClass $product2): \stdClass
    {
        $result = clone $product1;
        foreach (array_keys(get_object_vars($product2)) as $fieldName) {
            $result->{$fieldName} = $product2->{$fieldName};
        }
        return $result;
    }

    private static function objectToArray(\stdClass $object): array
    {
        $encoded = json_encode($object);
        Assert::string($encoded);
        return json_decode($encoded, true);
    }

    private static function arrayToObject(array $array): \stdClass
    {
        $encoded = json_encode($array);
        Assert::string($encoded);
        return json_decode($encoded, false);
    }

    private static function convertCustomAttributesToAssociativeArray(array $array): array
    {
        if (empty($array['custom_attributes'])) {
            return $array;
        }
        $customAttributesAssociative = [];
        foreach ($array['custom_attributes'] as $customAttribute) {
            $customAttributesAssociative[$customAttribute['attribute_code']] = $customAttribute['value'];
        }
        $array['custom_attributes'] = $customAttributesAssociative;
        return $array;
    }

    private static function convertCustomAttributesFromAssociativeArray(array $array): array
    {
        if (empty($array['custom_attributes'])) {
            return $array;
        }
        $customAttributesNotAssociative = [];
        foreach ($array['custom_attributes'] as $attributeCode => $attributeValue) {
            $customAttributesNotAssociative[] = ['attribute_code' => $attributeCode, 'value' => $attributeValue];
        }
        $array['custom_attributes'] = $customAttributesNotAssociative;
        return $array;
    }
}
