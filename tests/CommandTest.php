<?php

declare(strict_types=1);

namespace Survos\MediaTopicsBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application(new TestKernel('test', true));
    }

    public function testTopLevel(): void
    {
        $out = $this->runJson('media-topics:show', []);
        self::assertSame('2026-07-02', $out['version']);
        self::assertCount(17, $out['children']);
    }

    public function testOneTopicInAnotherLocale(): void
    {
        $out = $this->runJson('media-topics:show', ['topic' => 'medtop:20000479', '--locale' => 'es']);
        self::assertSame('Política de atención de salud', $out['label']);
        self::assertSame(['Q1519812'], $out['wikidata']);
        self::assertCount(2, $out['path']);
    }

    public function testUnknownTopicFails(): void
    {
        $tester = new CommandTester($this->application->find('media-topics:show'));
        self::assertSame(1, $tester->execute(['topic' => 'nope']));
    }

    public function testSearch(): void
    {
        $out = $this->runJson('media-topics:search', ['query' => 'healthcare policy', '--locale' => 'en-US', '--limit' => '3']);
        self::assertSame('medtop:20000479', $out['topics'][0]['qcode']);
        self::assertLessThanOrEqual(3, count($out['topics']));
    }

    public function testGenres(): void
    {
        self::assertCount(51, $this->runJson('media-topics:genres', [])['genres']);
        self::assertSame('genre:Obituary', $this->runJson('media-topics:genres', ['query' => 'obituary'])['genres'][0]['qcode']);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    private function runJson(string $command, array $input): array
    {
        $tester = new CommandTester($this->application->find($command));
        $tester->execute($input + ['--format' => 'json']);
        $tester->assertCommandIsSuccessful();

        return json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
