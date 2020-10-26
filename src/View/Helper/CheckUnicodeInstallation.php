<?php declare(strict_types=1);

namespace ArchiveRepertory\View\Helper;

use ArchiveRepertory\Helpers;
use Laminas\View\Helper\AbstractHelper;

class CheckUnicodeInstallation extends AbstractHelper
{
    public function __invoke()
    {
        return Helpers::checkUnicodeInstallation();
    }
}
