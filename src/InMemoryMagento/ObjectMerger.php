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
                //Special case for arrays of ['attribute_code' => 'xxx', 'value' => 'yyy'] elements
                if (self::hasFieldOrKey($value, 'attribute_code')) {
                    $attributeCode = self::getValue($value, 'attribute_code');

                    if (is_array($result)) {
                        foreach ($result as $key1 => $value1) {
                            if (self::getValue($value1, 'attribute_code') == $attributeCode) {
                                $result[$key1] = $value;
                                continue 2;
                            }
                        }

                        $result[] = $value;
                        continue;
                    }
                }

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
            if (is_numeric($fieldName)) {
                $mergeInto[] = $value;
            } else {
                $mergeInto[$fieldName] = $value;
            }
        } else {
            throw new \Exception("Cannot set a value into a field of a scalar value");
        }
    }

    private static function hasFieldOrKey($elem, $key)
    {
        if ($elem instanceof \stdClass) {
            return isset($elem->{$key});
        } elseif (is_array($elem)) {
            return array_key_exists($key, $elem);
        } else {
            return false;
        }
    }

    private static function getValue($elem, $key)
    {
        if ($elem instanceof \stdClass) {
            return $elem->{$key};
        } elseif (is_array($elem)) {
            return $elem[$key];
        } else {
            throw new \Exception("Can't access a field of a scalar value");
        }
    }
}
