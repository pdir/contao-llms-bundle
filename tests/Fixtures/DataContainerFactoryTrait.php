<?php

namespace Pdir\ContaoLlmsBundle\Tests\Fixtures;

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Creates a data container with the given ID and table without the Contao
 * framework.
 */
trait DataContainerFactoryTrait
{
    private function createDataContainer(int|null $id, string $table): DataContainer
    {
        $dc = (new \ReflectionClass(DC_Table::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DataContainer::class, 'intId'))->setValue($dc, $id);
        (new \ReflectionProperty(DataContainer::class, 'strTable'))->setValue($dc, $table);

        return $dc;
    }
}
