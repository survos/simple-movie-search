<?php

namespace App\Command;

use App\Entity\Movie;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;

use Meilisearch\Client;
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

        // this only works because we know there are no linefeeds in the file.
        $fullFilename = $this->dataDir . $filename;
        $lineCount = $this->getLineCount($fullFilename);

        $io->warning("Loading " . $fullFilename . " get $limit of $lineCount");
        $progressBar = new ProgressBar($io->output(), $lineCount);

        $count = 0;

        $config = $this->setupSchemaFromHeaders($fullFilename);
        $writer = Writer::createFromString();
        $csvWriter = new CsvWriter($writer);

        $headers = $config['outputSchema'];

        $formatter = function (array $row) use ($headers): array {
            return array_merge($row, $headers);
            return array_map('strtoupper', $row);
        };
        $writer = Writer::createFromFileObject(new \SplTempFileObject());
        $writer->insertOne($headers);
//        $writer->addFormatter($formatter);
////        $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
//        $writer->insertOne(['john'=>'doe']);

//        dd($writer->toString());

//        $movieCsv = new CsvDatabase('movie.csv', 'imdbId');
//        $movieCsv->reset();
//        foreach ([
//                     'primaryTitle', 'originalTitle', 'titleType:rel.type', 'isAdult:bool', 'runtimeMinutes:int', 'startYear:int', 'genre:rel.genre'
//                 ] as $header) {
//            $movieCsv->addHeader($header);
//    }
//        // keyNames?  Create multiple lookup cache?
//        $genreCsv = new CsvDatabase('genre.csv', 'code', ['code:id', 'label']);

        // define the schema one field/column at a time
//        $movieCsv->addHeader('imdb:id')
//            ->addHeader()


        $client = new Client('http://127.0.0.1:7700', 'masterKey');
        $index = $client->index('movies');


        $parser = $this->getParser($config);
//        $rows = $csv->getRecords();
        $rows = $parser->fromFile($fullFilename);
        foreach ($rows as $row) {
            $index->addDocuments([$row], 'id');
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

    public function addCat($type, $value, $settings)
    {
        $delim = $settings['delim']??'|';
        foreach (explode($delim, $value) as $v) {
            $slug = u($v)->snake()->toString();
            $this->cat[$type][$slug] = $v;
        }
    }

    public function addRelatedCore($type, $value, array $settings=[])
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
        Parser::registerType('rel', function ($value, $core, $settings) use ($config)  {
//            dd($value, $core, 'rel');
//            dd($config, $value, $core, $settings);
            $delimiter = $settings['delim']??'|';
            $this->addRelatedCore($core, $value, $settings);
//            return $value;
            return explode($delimiter, trim($value));
        });
        Parser::registerType('db', function ($value, $internalCode) {
            // based on internalCode, do something.
            return $value;
        });
        Parser::registerType('cat', function ($value, $catType, $settings) {
//            dd($value, $catType, 'cat');
            $this->addCat($catType, $value, $settings);
            return $value;
            return DB::table($table)->findById($value);
        });
        $parser = new Parser($config);
        return $parser;
    }

    /**
     * @param string $fullFilename
     * @return array
     * @throws \League\Csv\Exception
     * @throws \League\Csv\InvalidArgument
     * @throws \League\Csv\UnavailableStream
     */
    public function setupSchemaFromHeaders(string $fullFilename): array
    {
        $fieldNameDelimited = ':';
        // input to output map
        $map = Yaml::parse(<<<END
map:
    /tconst/i: id:db.code
    /primary_title/i: title:db.label
    /year/i: int
    /runtime/i: int?units=min
    /type/i: type:cat.type?label=Tipo
    /adult/i: adult:bool?permission=admin
    /genre/i: rel.genre?delim=,
END
        );

        $csv = Reader::createFromPath($fullFilename, 'r');
        $csv->setDelimiter("\t")
            ->setHeaderOffset(0) // use the first line as header for rows
        ;

        $outputSchema = [];
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

                    if (!str_contains($rule, '?')) {
                        $rule .= '?';
                    }
                    [$dottedConfig, $settingsString] = explode('?', $rule);
                    $settings = Parser::parseQueryString($settingsString);
                    $values = explode('.', $dottedConfig);
                    $type = array_shift($values);
                    if ($type == '') {
                        $type = 'string';
                    }
                    $outputHeader = $settings['header']??$newColumn;
                    $outputHeader .= $fieldNameDelimited . $dottedConfig;
                    if ($columnType) {
//                        $outputHeader .= ':' . $columnType;
                    }
                    unset($settings['header']);
                    if (count($settings)) {
//                        $columnType = json_encode($settings);
//                        $outputHeader .= ':' . $columnType;
                        $outputHeader .= '?' . http_build_query($settings);
//                        dd($outputHeader);
                    }
                    $outputSchema[$newColumn] = array_merge([
                        'type' => $dottedConfig,
                        ],
                        $settings);

                    $columnType = $outputHeader;
//                    dd($type, $rule, $settings, $values, $columnType, $outputHeader);
                    assert($type);

//                    dd($columnType, $rule);
                    break;
                }
            }
            $schema[$newColumn] = $columnType;
        }
        file_put_contents('schema.json', json_encode($outputSchema, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
//        dd($schema, $outputSchema, json_encode($outputSchema, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
        // the input config
        $config = [
            'delimiter' => "\t",
            'skipTitle' => true,
            'outputSchema' => $outputSchema,
            // the map, ignores the row headers, so this must be the correct order~
            'schema' => $schema,
            'valueRules' => [
                '\N' => null
            ]
        ];
        return $config;
    }

}
