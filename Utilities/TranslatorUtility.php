<?php
/*
 * This file is part of the Sidus/BaseBundle package.
 *
 * Copyright (c) 2015-2021 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\Utilities;

use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see    TranslatorUtility::tryTranslate
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class TranslatorUtility
{
    /**
     * Will check the translator for the provided keys and humanize the code if no translation is found
     *
     * @param TranslatorInterface $translator
     * @param string|array        $tIds
     * @param array               $parameters
     * @param string              $fallback
     * @param bool                $humanizeFallback
     *
     * @return string
     */
    public static function tryTranslate(
        TranslatorInterface $translator,
        $tIds,
        array $parameters = [],
        $fallback = null,
        $humanizeFallback = true
    ) {
        foreach ((array) $tIds as $tId) {
            try {
                if ($translator instanceof TranslatorBagInterface) {
                    if ($translator->getCatalogue()->has($tId)) {
                        return $translator->trans($tId, $parameters);
                    }
                } elseif ($translator instanceof TranslatorInterface) {
                    $label = $translator->trans($tId, $parameters);
                    if ($label !== $tId) {
                        return $label;
                    }
                }
            } catch (InvalidArgumentException $e) {
                // Do nothing
            }
        }

        if (null === $fallback) {
            return null;
        }
        if (!$humanizeFallback) {
            return $fallback;
        }
        $pattern = '/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]|\d{1,}/';

        return str_replace('_', ' ', preg_replace($pattern, ' $0', $fallback));
    }
}
