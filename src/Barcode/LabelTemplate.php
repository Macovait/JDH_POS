<?php
/**
 * Label Template - Barcode label template definitions
 * Stub implementation for Jakababa POS
 */

namespace JDH\POS\Barcode;

class LabelTemplate
{
    public static function getDefaults(): array
    {
        return [
            'k5'   => ['name' => 'K5 (38x16mm)', 'width' => 38, 'height' => 16],
            'k22'  => ['name' => 'K22 (51x25mm)', 'width' => 51, 'height' => 25],
            'ka2'  => ['name' => 'KA2 (51x13mm)', 'width' => 51, 'height' => 13],
            'k38'  => ['name' => 'K38 (76x38mm)', 'width' => 76, 'height' => 38],
            'k11'  => ['name' => 'K11 (19x13mm)', 'width' => 19, 'height' => 13],
            'ka1'  => ['name' => 'KA1 (25x19mm)', 'width' => 25, 'height' => 19],
            'k27'  => ['name' => 'K27 (51x38mm)', 'width' => 51, 'height' => 38],
            'k36'  => ['name' => 'K36 (76x51mm)', 'width' => 76, 'height' => 51],
        ];
    }
}
