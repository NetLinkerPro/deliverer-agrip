<?php

namespace NetLinker\DelivererAgrip\Tests\Helpers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Tests\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class WordReplacer extends TestCase
{
    /** @var Finder $finder */
    protected $finder;

    /** @var Filesystem $file */
    protected $file;

    const WORD_FROM = 'agrip';

    const WORD_TO = 'agrip';

    public function testRun()
    {
        if (self::WORD_FROM === self::WORD_TO){
            throw new \Exception('Words is replaced.');
        }
        $this->finder = new Finder();
        $this->file = new Filesystem();
        $this->replaceWordFilename();
        $this->replaceWordContent();
        $this->deleteDatabaseSqlite();
        $this->archiveServices();
        $this->archiveTests();
        $this->runComposerDumpAutoload();
        $this->deleteGitDirectory();
        $this->deleteDataResource();
        $this->assertTrue(true);
    }

    public function files()
    {
        $moduleDir = $this->moduleDir();
        return $this->finder
            ->ignoreDotFiles(false)
            ->ignoreVCSIgnored(true)
            ->in($moduleDir);

    }

    public function moduleDir()
    {
        return realpath(__DIR__ . '/../../');
    }

    private function deleteDatabaseSqlite()
    {
        $this->file->delete(__DIR__ . '/../database/database.sqlite');
    }

    private function deleteGitDirectory()
    {
        $this->file->deleteDirectory(__DIR__ . '/../../.git');
    }

    private function replaceWordFilename()
    {
        $files = $this->files();
        foreach ($files as $file) {
            $pathRelative = $file->getRelativePathname();
            $pathRelativeReplaced = $this->replace($pathRelative);
            if ($pathRelative !== $pathRelativeReplaced) {
                $pathAbsolute = $this->moduleDir() . '/' . $pathRelative;
                $pathAbsoluteReplaced = $this->moduleDir() . '/' . $pathRelativeReplaced;
                $this->file->move($pathAbsolute, $pathAbsoluteReplaced);
            }
        }
    }

    private function replace(string $content)
    {
        $fromLower = mb_strtolower(self::WORD_FROM);
        $toLower = mb_strtolower(self::WORD_TO);
        $fromFirstUpper = Str::ucfirst($fromLower);
        $toFirstUpper = Str::ucfirst($toLower);
        $fromUpper = mb_strtoupper($fromLower);
        $toUpper = mb_strtoupper($toLower);
        return str_replace([
            $fromLower,
            $fromFirstUpper,
            $fromUpper
        ], [
            $toLower,
            $toFirstUpper,
            $toUpper
        ], $content);
    }

    private function replaceWordContent()
    {
        $files = $this->files();
        foreach ($files as $file) {
            if ($file->isFile()) {
                $pathAbsolute = $file->getRealPath();
                $content = $this->file->get($pathAbsolute);
                $contentReplaced = $this->replace($content);
                if ($content !== $contentReplaced) {
                    $this->file->put($pathAbsolute, $contentReplaced);
                }
            }
        }
    }

    private function runComposerDumpAutoload()
    {
        $process = new Process(['composer', 'dump-autoload']);
        $process->run();
        $process->wait();
    }

    private function archiveServices()
    {
        $serviceDirectories = [
            'DataProducts',
            'ListCategories',
            'ListProducts',
            'WebapiClients',
            'WebsiteClients',
        ];
        foreach ($serviceDirectories as $serviceDirectory) {
            $pathDirectoryServices = sprintf('%s/../../src/Sections/Sources/Services/%s', __DIR__, $serviceDirectory);
            $files = File::files($pathDirectoryServices);
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $pathTarget = sprintf('%s/Archives/%s', $pathDirectoryServices, $file->getFilename());
                    $fromPath = $file->getRealPath();
                    File::copy($fromPath, $pathTarget);
                    $content = File::get($pathTarget);
                    $content = str_replace(sprintf('namespace NetLinker\Deliverer%s\Sections\Sources\Services\%s', Str::ucfirst(self::WORD_TO), $serviceDirectory), sprintf('namespace NetLinker\Deliverer%s\Sections\Sources\Services\%s\Archives', Str::ucfirst(self::WORD_TO), $serviceDirectory), $content);
                    File::put($pathTarget, $content);
                }
            }
        }
    }

    private function archiveTests()
    {
        $serviceDirectories = [
            'DataProducts',
            'ListCategories',
            'ListProducts',
            'WebapiClients',
            'WebsiteClients',
        ];
        foreach ($serviceDirectories as $serviceDirectory) {
            $pathDirectoryServices = sprintf('%s/../../tests/Sections/Sources/Services/%s', __DIR__, $serviceDirectory);
            $files = File::files($pathDirectoryServices);
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $pathTarget = sprintf('%s/Archives/%s', $pathDirectoryServices, $file->getFilename());
                    $fromPath = $file->getRealPath();
                    File::copy($fromPath, $pathTarget);
                    $content = File::get($pathTarget);
                    $content = str_replace(sprintf('namespace NetLinker\Deliverer%s\Tests\Sections\Sources\Services\%s', Str::ucfirst(self::WORD_TO), $serviceDirectory), sprintf('namespace NetLinker\Deliverer%s\Tests\Sections\Sources\Services\%s\Archives', Str::ucfirst(self::WORD_TO), $serviceDirectory), $content);
                    File::put($pathTarget, $content);
                }
            }
        }
    }

    private function deleteDataResource()
    {
        $this->file->deleteDirectory(__DIR__ . '/../../resources/data');
    }
}