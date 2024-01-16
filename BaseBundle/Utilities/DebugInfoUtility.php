<?php
/*
 * This file is part of the Sidus/BaseBundle package.
 *
 * Copyright (c) 2015-2021 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Base\Utilities;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\CutStub;
use function get_class;
use function in_array;

/**
 * Easy debug info builder with exclusion support
 */
class DebugInfoUtility
{
    /**
     * @param object $a
     * @param array  $excludedProperties
     *
     * @return array
     */
    public static function debugInfo($a, array $excludedProperties = [])
    {
        $a = Caster::castObject($a, get_class($a), false);
        foreach ($a as $k => $v) {
            if (in_array($k, $excludedProperties, true)) {
                $a[$k] = new CutStub($a[$k]);
            }
        }

        return $a;
    }
}
