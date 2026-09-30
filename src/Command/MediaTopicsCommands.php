<?php

declare(strict_types=1);

namespace Survos\MediaTopicsBundle\Command;

use Survos\CommandBundle\Attribute\AsAgentTool;
use Survos\MediaTopics\Concept;
use Survos\MediaTopics\ConceptSet;
use Survos\MediaTopics\Genres;
use Survos\MediaTopics\MediaTopic;
use Survos\MediaTopics\MediaTopics;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MediaTopicsCommands
{
    public const string EXPORT_URL = 'https://cv.iptc.org/newscodes/%s/?format=json&lang=x-all';

    public function __construct(
        private readonly MediaTopics $topics,
        private readonly Genres $genres,
        private readonly string $defaultLocale = MediaTopics::DEFAULT_LOCALE,
        private readonly ?HttpClientInterface $http = null,
    ) {}

    #[AsCommand('media-topics:show', 'Show one IPTC Media Topic with its path and children, or the top-level topics')]
    #[AsAgentTool(readOnly: true, public: true)]
    public function show(
        SymfonyStyle $io,
        #[Argument('Topic id, qcode or URI, e.g. 20000479 or medtop:20000479; omit for the top level')] ?string $topic = null,
        #[Option('Locale for labels and definitions, e.g. en-GB, fr, es')] ?string $locale = null,
        #[Option('Output format: text or json')] string $format = 'text',
    ): int {
        $locale ??= $this->defaultLocale;
        if ($topic === null) {
            $data = ['version' => $this->topics->version(), 'topics' => count($this->topics), 'children' => $this->rows($this->topics->roots(), $locale)];
        } else {
            $found = $this->topics->get($topic);
            if ($found === null) {
                $io->error(sprintf('No Media Topic "%s".', $topic));

                return Command::FAILURE;
            }
            $data = $this->row($found, $locale) + [
                'uri' => $found->uri(),
                'retired' => $found->retired?->format('Y-m-d'),
                'subjectCodes' => $found->subjectCodes,
                'wikidata' => $found->wikidata,
                'path' => array_map(static fn (MediaTopic $t): string => $t->label($locale), $this->topics->ancestors($found->id)),
                'children' => $this->rows($this->topics->children($found->id), $locale),
            ];
        }
        if ($format === 'json') {
            $this->json($io, $data);

            return Command::SUCCESS;
        }
        if ($topic === null) {
            $io->title(sprintf('IPTC Media Topics %s: %d topics', $data['version'], $data['topics']));
        } else {
            $io->title(sprintf('%s  %s', $data['qcode'], implode(' > ', [...$data['path'], $data['label']])));
            $io->writeln($data['definition']);
            $io->definitionList(
                ['URI' => $data['uri']],
                ['Subject Codes' => implode(', ', $data['subjectCodes']) ?: '-'],
                ['Wikidata' => implode(', ', $data['wikidata']) ?: '-'],
                ['Retired' => $data['retired'] ?? 'no'],
            );
        }
        $this->table($io, $data['children']);

        return Command::SUCCESS;
    }

    #[AsCommand('media-topics:search', 'Find IPTC Media Topics by words in their label or definition')]
    #[AsAgentTool(readOnly: true, public: true)]
    public function search(
        SymfonyStyle $io,
        #[Argument('Words to look for')] string $query,
        #[Option('Maximum number of topics')] int $limit = 20,
        #[Option('Locale to search and show, e.g. en-GB, fr, es')] ?string $locale = null,
        #[Option('Output format: text or json')] string $format = 'text',
    ): int {
        $locale ??= $this->defaultLocale;
        $rows = array_map(
            fn (MediaTopic $t): array => $this->row($t, $locale) + ['path' => array_map(static fn (MediaTopic $a): string => $a->label($locale), $this->topics->ancestors($t->id))],
            $this->topics->search($query, $locale, $limit),
        );
        if ($format === 'json') {
            $this->json($io, ['topics' => $rows]);
        } else {
            $io->table(['qcode', 'topic', 'definition'], array_map(
                static fn (array $r): array => [$r['qcode'], implode(' > ', [...$r['path'], $r['label']]), mb_strimwidth($r['definition'], 0, 80, '…')],
                $rows,
            ));
        }

        return Command::SUCCESS;
    }

    #[AsCommand('media-topics:genres', 'List the IPTC Genres (Obituary, Review, Opinion, ...), or find them by words')]
    #[AsAgentTool(readOnly: true, public: true)]
    public function genres(
        SymfonyStyle $io,
        #[Argument('Words to look for in label or definition; omit to list every genre')] ?string $query = null,
        #[Option('Output format: text or json')] string $format = 'text',
    ): int {
        $rows = $this->rows($query === null ? $this->genres->all() : $this->genres->search($query, limit: 100), ConceptSet::DEFAULT_LOCALE);
        if ($format === 'json') {
            $this->json($io, ['version' => $this->genres->version(), 'genres' => $rows]);
        } else {
            $io->title(sprintf('IPTC Genres %s: %d genres', $this->genres->version(), count($this->genres)));
            $this->table($io, $rows);
        }

        return Command::SUCCESS;
    }

    #[AsCommand('media-topics:download', 'Fetch the current Media Topics (or Genre) release from IPTC into a file; set survos_media_topics.file (or genre_file) to use it')]
    public function download(
        SymfonyStyle $io,
        #[Argument('Where to write the JSON export')] string $file,
        #[Option('Fetch the Genre vocabulary instead of Media Topics')] bool $genre = false,
    ): int {
        if ($this->http === null) {
            $io->error('Needs symfony/http-client.');

            return Command::FAILURE;
        }
        $json = $this->http->request('GET', sprintf(self::EXPORT_URL, $genre ? 'genre' : 'mediatopic'))->getContent();
        $tmp = $file.'.tmp';
        file_put_contents($tmp, $json);
        // Parse before replacing anything: a truncated or changed export must not become the vocabulary.
        $fetched = $genre ? Genres::load($tmp) : MediaTopics::load($tmp);
        $current = $genre ? $this->genres : $this->topics;
        rename($tmp, $file);
        $io->success(sprintf('%s: release %s, %d concepts (in use now: release %s, %d concepts).', $file, $fetched->version(), count($fetched), $current->version(), count($current)));

        return Command::SUCCESS;
    }

    /** @return array{id: string, qcode: string, label: string, definition: string} */
    private function row(Concept $topic, string $locale): array
    {
        return ['id' => $topic->id, 'qcode' => $topic->qcode(), 'label' => $topic->label($locale), 'definition' => $topic->definition($locale)];
    }

    /**
     * @param list<Concept> $topics
     * @return list<array{id: string, qcode: string, label: string, definition: string}>
     */
    private function rows(array $topics, string $locale): array
    {
        return array_map(fn (Concept $t): array => $this->row($t, $locale), $topics);
    }

    /** @param list<array{id: string, qcode: string, label: string, definition: string}> $rows */
    private function table(SymfonyStyle $io, array $rows): void
    {
        if ($rows !== []) {
            $io->table(['qcode', 'label', 'definition'], array_map(
                static fn (array $r): array => [$r['qcode'], $r['label'], mb_strimwidth($r['definition'], 0, 80, '…')],
                $rows,
            ));
        }
    }

    /** @param array<string, mixed> $data */
    private function json(SymfonyStyle $io, array $data): void
    {
        $io->writeln(json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }
}
