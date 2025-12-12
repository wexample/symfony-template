<?php

namespace Wexample\SymfonyTemplate\Twig;

use Exception;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;

class SystemExtension extends AbstractExtension
{
    public function __construct(private readonly KernelInterface $kernel)
    {

    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'system_version',
                [
                    $this,
                    'systemVersion',
                ]
            ),
        ];
    }

    /**
     * @throws Exception
     */
    public function systemVersion(
        string $versionFile = 'version.txt'
    ): ?string
    {
        $versionFilePath = $this->kernel->getProjectDir() . '/' . $versionFile;

        if (is_file($versionFilePath)) {
            return trim(file_get_contents($versionFilePath));
        }

        return null;
    }
}
