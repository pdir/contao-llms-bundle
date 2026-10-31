<?php

namespace Pdir\ContaoLlmsBundle\Tests\Fixtures;

use Contao\Model;

/**
 * Creates Contao models with the given data without a database (works with
 * the test helpers of Contao 5.3 and 6).
 */
trait ModelFactoryTrait
{
    /**
     * @template T of Model
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function createModel(string $class, array $data): Model
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Model::class, 'arrData'))->setValue($model, $data);

        // Contao 6 reads the default values of missing properties from the database
        if (property_exists(Model::class, 'arrColumnInfos')) {
            $columnInfos = new \ReflectionProperty(Model::class, 'arrColumnInfos');

            if (!$columnInfos->getValue()) {
                $columnInfos->setValue(null, ['tl_page' => []]);
            }
        }

        return $model;
    }
}
