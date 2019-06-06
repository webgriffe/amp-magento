<?php

declare(strict_types=1);

namespace Webgriffe\AmpMagento\InMemoryMagento;

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
        $productArray1 = self::objectToArray($product1);
        $productArray2 = self::objectToArray($product2);
        $productArray1 = self::convertCustomAttributesToAssociativeArray($productArray1);
        $productArray2 = self::convertCustomAttributesToAssociativeArray($productArray2);
        $merge = array_replace_recursive($productArray1, $productArray2);
        $merge = self::convertCustomAttributesFromAssociativeArray($merge);
        return self::arrayToObject($merge);
    }

    private static function objectToArray(\stdClass $object): array
    {
        return json_decode(json_encode($object), true);
    }

    private static function arrayToObject(array $array): \stdClass
    {
        return json_decode(json_encode($array), false);
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
