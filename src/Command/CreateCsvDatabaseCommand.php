<?php

namespace App\Command;

use App\Entity\Movie;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;

use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\CsvWriter;
use Survos\GridGroupBundle\Service\GridGroupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\IO;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use function Symfony\Component\String\u;

#[AsCommand('app:create-csv', 'Creates a CSV database from the movie file.  Uses CsvReader from league.  No doctrine')]
final class CreateCsvDatabaseCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes, RunsCommands, RunsProcesses;


    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
        private array $cat = [],
        private array $rel = [],
        string                                                   $name = null)
    {
        parent::__construct($name);
        $this->setHelp(<<<EOL
Given a CSV file with movie titles and genre, create 2 csv files with the appropriate relationships.

headers contain type hints, which can be used for MeiliSearch
EOL
);
    }

    public function __invoke(
        IO                                                 $io,
        #[Argument(description: 'filename')] string        $filename = 'title.small.tsv',
        #[Option(description: 'limit')] int                $limit = 10000,
        #[Option(description: 'batch size for flush')] int $batch = 1000,




    ): void
    {
        $slugger = new AsciiSlugger();

        $writer = Writer::createFromString();
        $csvWriter = new CsvWriter($writer);
        $headers = ['first', 'last', 'email'];
        $formatter = function (array $row) use ($headers): array {
            return array_merge($row, $headers);
            return array_map('strtoupper', $row);
        };
        $writer = Writer::createFromFileObject(new \SplTempFileObject());
        $writer->insertOne($headers);
        $writer->addFormatter($formatter);
        $writer->insertOne(['john', 'doe', 'john.doe@example.com']);

        dd($writer->toString());

        $movieCsv = new CsvDatabase('movie.csv', 'imdbId');
        $movieCsv->reset();
        foreach ([
                     'primaryTitle', 'originalTitle', 'titleType:rel.type', 'isAdult:bool', 'runtimeMinutes:int', 'startYear:int', 'genre:rel.genre'
                 ] as $header) {
            $movieCsv->addHeader($header);
    }
        // keyNames?  Create multiple lookup cache?
        $genreCsv = new CsvDatabase('genre.csv', 'code', ['code:id', 'label']);

        // define the schema one field/column at a time
//        $movieCsv->addHeader('imdb:id')
//            ->addHeader()


        // this only works because we know there are no linefeeds in the file.
        $fullFilename = $this->dataDir . $filename;
        $lineCount = $this->getLineCount($fullFilename);

        $io->warning("Loading " . $fullFilename . " get $limit of $lineCount");
        $progressBar = new ProgressBar($io->output(), $lineCount);

        $count = 0;

        $fieldNameDelimited = '/';
        $map = Yaml::parse(<<<END
map:
    /tconst/i: db.code?header=id
    /year/i: int
    /runtime/i: int?units=min
    /type/i: cat.type?label=Tipo
    /adult/i: bool?code=adult
    /genre/i: rel.genre?delim=,
END
);

        $csv = Reader::createFromPath($fullFilename, 'r');
        $csv->setDelimiter("\t")
            ->setHeaderOffset(0) // use the first line as header for rows
        ;

//        $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
//        $serializer = new Serializer([$normalizer]);
//        $personArray = $serializer->normalize(new Person());

        $header = $csv->getHeader();
        foreach ($header as $column) {
            // use the map!
            $newColumn = u($column)->snake()->toString(); //
            $columnType = 'string';
            foreach ($map['map'] as $regEx => $rule) {
                if (preg_match($regEx, $newColumn)) {
                    if (str_contains($rule, $fieldNameDelimited)) { // } && !str_starts_with($rule, 'array:')) {
                        [$newColumn, $rule] = explode($fieldNameDelimited, $rule, 2);
                    }
                    $columnType = $rule; // for now
                    break;
                }
            }
            $schema[$newColumn] = $columnType;
        }
        $config = [
            'delimiter' => "\t",
            'skipTitle' => true,
            // the map, ignores the row headers, so this must be the correct order~
            'schema' => $schema,
            'valueRules' => [
                '\N' => null
            ]
        ];


        $parser = $this->getParser($config);
//        $rows = $csv->getRecords();
        $rows = $parser->fromFile($fullFilename);
        foreach ($rows as $row) {
            $count++;
            if ($limit && ($count > $limit)) {
                break;
            }
        }
        if (0) {
            $imdbId = (int)u($row['tconst'])->after('tt')->toString();
            if (!$movieCsv->has($imdbId)) {
                // the movie data should come from a json schema.  This will do for now.
                $movie = [];
                // no mapping, just use the same for now.
                foreach (['primaryTitle', 'originalTitle', 'titleType', 'isAdult', 'runtimeMinutes', 'startYear', 'genres'] as $key) {

                    GridGroupService::assertKeyExists($key, $row);
                    $value = $row[$key];
                    if ($value == '\N') {
                        $value = null;
                    }
                    switch ($key) {
                        case 'genres':
                            foreach (explode(',', $value ?? '') as $genre) {
                                $code = $slugger->slug($genre);
                                if (!$genreCsv->has($code)) {
                                    $genreRecord = [
                                        'label' => $genre
                                    ];
                                    $genreCsv->set($code, $genreRecord);
                                }
                            }
                            break;
                        default:
                            $movie[$key] = $value;
                    }
                }

                $movieCsv->set($imdbId, $movie);
            }

            $progressBar->advance();
        }
        $progressBar->finish();
        dd($this->cat, $this->rel, $count);
        $io->success("Done.");
    }

    public function addCat($type, $value)
    {
        $slug = u($value)->snake()->toString();
        $this->cat[$type][$slug] = $value;
    }

    public function addRelatedCore($type, $value)
    {
        $slug = u($value)->snake()->toString();
        $this->rel[$type][$slug] = $value;
    }

    /**
     * @param string $fullFilename
     * @return int
     */
    public function getLineCount(string $fullFilename): int
    {
        $process = (new Process(['wc', '-l', $fullFilename]));
        $process->run();
        $output = $process->getOutput();
        $lineCount = (int)u($output)->before(' ')->toString();
        return $lineCount;
    }

    /**
     * @param array $config
     * @return Parser
     */
    public function getParser(array $config): Parser
    {
        Parser::registerType('rel', function ($value, $core)  {
            dd($value, $core, 'rel');
            $this->addRelatedCore($core, $value);
            return explode($delimiter, trim($string));
        });
        Parser::registerType('cat', function ($value, $table) {
            $this->addCat($table, $value);
            return $value;
            return DB::table($table)->findById($value);
        });
        $parser = new Parser($config);
        return $parser;
    }

}
