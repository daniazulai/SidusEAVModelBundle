<?php
/*
 * This file is part of the Sidus/EAVModelBundle package.
 *
 * Copyright (c) 2015-2019 Vincent Chalnot
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sidus\EAVModelBundle\DataGrid;

use Sidus\DataGridBundle\Model\Column;
use Sidus\DataGridBundle\Renderer\ColumnLabelRendererInterface;
use Symfony\Contracts\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Overriding base column label rendering for Sidus/DataGridBundle v2.0
 *
 * This code is written for PHP7 because it can only work with the Sidus/DataGridBundle that supports PHP7+ only
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class EAVColumnLabelRenderer implements ColumnLabelRendererInterface
{
    /** @var ColumnLabelRendererInterface */
    protected $baseRenderer;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param ColumnLabelRendererInterface $baseRenderer
     * @param TranslatorInterface          $translator
     */
    public function __construct(ColumnLabelRendererInterface $baseRenderer, TranslatorInterface $translator)
    {
        $this->baseRenderer = $baseRenderer;
        $this->translator = $translator;
    }

    /**
     * @param Column $column
     *
     * @throws \Symfony\Contracts\Translation\Exception\InvalidArgumentException
     * @throws \Sidus\EAVModelBundle\Exception\MissingAttributeException
     *
     * @return string
     */
    public function renderColumnLabel(Column $column): string
    {
        // If already defined in translations, ignore
        $key = "datagrid.{$column->getDataGrid()->getCode()}.{$column->getCode()}"; // Same as base logic
        if ($column->getLabel()
            || ($this->translator instanceof TranslatorBagInterface && $this->translator->getCatalogue()->has($key))
        ) {
            return $this->baseRenderer->renderColumnLabel($column);
        }

        $queryHandler = $column->getDataGrid()->getQueryHandler();
        // EAVFilterBundle might not be installed
        /** @noinspection ClassConstantCanBeUsedInspection */
        if (!is_a($queryHandler, 'Sidus\EAVFilterBundle\Query\Handler\EAVQueryHandlerInterface')) {
            return $this->baseRenderer->renderColumnLabel($column);
        }

        /** @var \Sidus\EAVFilterBundle\Query\Handler\EAVQueryHandlerInterface $queryHandler */
        $family = $queryHandler->getFamily();
        if (!$family->hasAttribute($column->getCode())) {
            return $this->baseRenderer->renderColumnLabel($column);
        }

        return $family->getAttribute($column->getCode())->getLabel();
    }
}
