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

    /**
     * @throws \Exception
     */
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
                if (self::hasFieldOrKey($value, 'attribute_code')) {
                    $result = self::mergeProductAttributes($value, $result);
                } elseif (self::hasFieldOrKey($value, 'customer_group_id') && self::hasFieldOrKey($value, 'qty')) {
                    $result = self::mergeTierPrices($value, $result);
                } else {
                    self::mergeValue($result, $key, $value);
                }
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


    /**
     * @param $value
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    private static function mergeProductAttributes($value, $result)
    {
        $attributeCode = (string)self::getValue($value, 'attribute_code');

        if (is_array($result)) {
            foreach ($result as $key1 => $value1) {
                if ((string)self::getValue($value1, 'attribute_code') === $attributeCode) {
                    $result[$key1] = $value;
                    return $result;
                }
            }

            $result[] = $value;
            return $result;
        }
        return $result;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private static function mergeTierPrices($value, $result)
    {
        $customerGroupId = (int)self::getValue($value, 'customer_group_id');
        $qty = (int)self::getValue($value, 'qty');
        if (is_array($result)) {
            foreach ($result as $key1 => $value1) {
                if ((int)self::getValue($value1, 'customer_group_id') === $customerGroupId &&
                    (int)self::getValue($value1, 'qty') == $qty) {
                    $result[$key1] = $value;
                    return $result;
                }
            }

            $result[] = $value;
            return $result;
        }
        return $result;
    }
}
