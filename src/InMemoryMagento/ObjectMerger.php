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
        return self::mergeGeneric($product1, $product2);
    }

    private static function mergeGeneric($elem1, $elem2)
    {
        if ($elem1 instanceof \stdClass) {
            $result = clone $elem1;
        } elseif (is_array($elem1)) {
            $result = $elem1;
        } else {
            return $elem2;
        }

        if ($elem2 instanceof \stdClass) {
            foreach (array_keys(get_object_vars($elem2)) as $fieldName) {
                self::mergeValue($result, $fieldName, $elem2->{$fieldName});
            }
        } elseif (is_array($elem2)) {
            foreach ($elem2 as $key => $value) {
                self::mergeValue($result, $key, $value);
            }
        } else {
            //Non-object and non-array values. Can't merge, and in this case we must give precedence to the second
            //element
            return $elem2;
        }

        return $result;
    }

    private static function mergeValue(&$mergeInto, $fieldName, $value)
    {
        if ($mergeInto instanceof \stdClass) {
            if (isset($mergeInto->{$fieldName})) {
                $value = self::mergeGeneric($mergeInto->{$fieldName}, $value);
            }
            $mergeInto->{$fieldName} = $value;
        } elseif (is_array($mergeInto)) {
            if (array_key_exists($fieldName, $mergeInto)) {
                $value = self::mergeGeneric($mergeInto[$fieldName], $value);
            }
            $mergeInto[$fieldName] = $value;
        } else {
            throw new \Exception("Cannot set a value into a field of a scalar value");
        }
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
