# survos/media-topics-bundle

[IPTC Media Topics](https://iptc.org/standards/media-topics/) and IPTC Genre in a Symfony app: the
vocabularies as autowirable services, and console commands to browse and search it. The vocabulary itself, and
everything you can do with it, is in [`survos/media-topics`](../../lib/media-topics/README.md).

```bash
composer require survos/media-topics-bundle
```

## Service

```php
use Survos\MediaTopics\Genres;
use Survos\MediaTopics\MediaTopics;

final class ArticleController
{
    public function __construct(
        private readonly MediaTopics $topics,
        private readonly Genres $genres,
    ) {}
}
```

## Commands

```bash
bin/console media-topics:show                       # the 17 top-level topics
bin/console media-topics:show medtop:20000479       # one topic: path, definition, mappings, children
bin/console media-topics:show 20000479 --locale=es
bin/console media-topics:search "healthcare policy"
bin/console media-topics:genres                     # the IPTC Genres: Obituary, Review, Opinion, ...
bin/console media-topics:genres opinion
bin/console media-topics:download var/mediatopic.json
bin/console media-topics:download var/genre.json --genre
```

`show`, `search` and `genres` take `--format=json`, and are public read-only agent tools when
`survos/command-bundle` is installed.

`download` fetches the current release from IPTC (needs `symfony/http-client`) and checks that it
parses before replacing the file. Point the bundle at it to use it:

```yaml
# config/packages/survos_media_topics.yaml
survos_media_topics:
    file: '%kernel.project_dir%/var/mediatopic.json'   # default: the release pinned in survos/media-topics
    # genre_file: '%kernel.project_dir%/var/genre.json'
    locale: en-GB                                      # what the commands show when no --locale is given
```

## Loading topics into your own table

An app that keeps topics in Doctrine (for a tree widget, or foreign keys from articles) can fill
its entity from the service. Topics come parents-first, so each parent already exists:

```php
foreach ($topics->all(includeRetired: true) as $topic) {
    $row = $em->find(Topic::class, $topic->id) ?? new Topic($topic->id);
    $row->setName($topic->label())->setDescription($topic->definition());
    $row->setParent($topic->parentId ? $em->getReference(Topic::class, $topic->parentId) : null);
    $em->persist($row);
}
$em->flush();
```

Include retired topics so existing references keep resolving.

## License

MIT. The vocabulary is © IPTC, licensed CC BY 4.0; credit IPTC in applications that embed it.
