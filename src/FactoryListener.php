<?php

namespace Datashaman\PHPUnit;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

class FactoryListener implements TestListener
{
    use TestListenerDefaultImplementation;
}
