<?php

namespace Backstage\Favicon\Tests;

use Backstage\Favicon\FaviconServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    private string $diskRoot;

    protected function setUp(): void
    {
        $this->diskRoot = sys_get_temp_dir().'/favicon-tests-'.uniqid();
        mkdir($this->diskRoot, recursive: true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FaviconServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => $this->diskRoot,
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);

        $app['config']->set('favicon.disk', 'public');
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \FilesystemIterator($directory);

        foreach ($items as $item) {
            $item->isDir() ? $this->deleteDirectory($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
