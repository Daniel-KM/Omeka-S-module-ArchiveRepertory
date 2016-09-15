<?php

namespace ArchiveRepertory\View\Helper;

use Zend\View\Helper\AbstractHelper;
use ArchiveRepertory\Helpers;

class CheckUnicodeInstallation extends AbstractHelper
{
    public function __invoke()
    {
        return Helpers::checkUnicodeInstallation();
    }
}
