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
                if ($fieldName === 'custom_attributes') {
                    $result->{$fieldName} = self::mergeCustomAttributes(
                        $result->{$fieldName} ?? [],
                        $elem2->{$fieldName}
                    );
                    continue;
                }
                if ($fieldName === 'tier_prices') {
                    $result->{$fieldName} = $elem2->{$fieldName};
                    continue;
                }

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
            if (is_numeric($fieldName)) {
                $mergeInto[] = $value;
            } else {
                $mergeInto[$fieldName] = $value;
            }
        } else {
            throw new \Exception("Cannot set a value into a field of a scalar value");
        }
    }

    /**
     * @throws \Exception
     */
    private static function mergeCustomAttributes(array $existing, array $new): array
    {
        return array_values(
            array_merge(
                array_combine(
                    array_column($existing, 'attribute_code'),
                    $existing
                ),
                array_combine(
                    array_column($new, 'attribute_code'),
                    $new
                )
            )
        );
    }
}
