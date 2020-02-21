<?php
declare(strict_types=1);

namespace ArrayIterator\Service\Module\GoogleModule;

use ArrayIterator\Service\Core\Module\AbstractModule;

/**
 * Class GoogleModule
 * @package ArrayIterator\Service\Module\GoogleModule
 */
class GoogleModule extends AbstractModule
{
    protected $moduleName = 'Google Module';

    /**
     * {@inheritDoc}
     */
    public function onInit()
    {
        $this->registerObjectAutoloader();
    }
}
