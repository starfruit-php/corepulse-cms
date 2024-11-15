<?php

namespace CorepulseBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;

class CorepulseBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }


    public function getCssPaths(): array
    {
        return [
            '/bundles/corepulse/css/style.css',
        ];
    }

    public function getJsPaths(): array
    {
        return [
            '/bundles/corepulse/js/pimcore/extendPanel.js',
            '/bundles/corepulse/js/pimcore/startup.js',
        ];
    }

    public function getInstaller(): ?Installer
    {
        return $this->container->get(Installer::class);
    }
}
