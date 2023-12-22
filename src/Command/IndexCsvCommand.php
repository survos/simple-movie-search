<?php

namespace App\Command;

use App\Entity\Movie;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;

use Limenius\Liform\Liform;
use Meilisearch\Client;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\CsvWriter;
use Survos\GridGroupBundle\Service\GridGroupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
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

#[AsCommand('app:index-csv', 'Index a CSV file.  Uses CsvReader and CsvParser')]
final class IndexCsvCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes, RunsCommands, RunsProcesses;


    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
        private FormFactoryInterface $formFactory,
        private Liform $liform,
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
        #[Argument(description: 'filename')] string        $filename = 'title.basics.tsv',
        #[Option(description: 'limit')] int                $limit = 1000,
        #[Option(description: 'batch size for flush')] int $batch = 100,




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
        $filterableSortable = $config['filterableSortable'];
        unset($config['filterableSortable']);
        $io->success("Schema file written: " . $config['outputSchemaFilename']);
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
        $index = $client->index('movie');


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
        $progressBar->finish();

        $index->updateSettings([
            'filterableAttributes' => $filterableSortable,
            'sortableAttributes' => $filterableSortable
        ]);

//        dd($this->cat, $this->rel, $count);
        $io->success("Done.");
    }

    public function addCat($type, $value, $settings)
    {
        $slug = u($value)->snake()->toString();
        $this->cat[$type][$slug] = $value;
    }

    public function addRelatedCore($type, $value, array $settings=[])
    {
        $delim = $settings['delim']??'|';
        foreach (explode($delim, $value) as $v) {
            $slug = u($v)->snake()->toString();
            $this->rel[$type][$slug] = $v;
        }
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
        });

        $schema = Parser::createConfigFromMap();
        $schema->setValueRules($test['valueRules'] ?? []);
        $parser = new Parser($schema);
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
    /year/i: int?max=2024
    /runtime_in_minutes/i: runtime:int?units=min
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

        $formBuilder =  $this->formFactory->createBuilder(FormType::class);

//        $form = $this->createFormBuilder()


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
                    $internalCode = array_shift($values);
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
                    $propertyType = TextType::class;
                    $propertyType = match($type) {
                        'db' => match($internalCode) {
                            'code' => TextType::class,
                            'label' => TextareaType::class,
                            'description' => TextareaType::class,
                            default => assert(false, $internalCode)

                        },
                        'rel'  =>  CollectionType::class,
                        'cat' => TextType::class, // really a relationship to the cat table -- choice?
                        'bool' => CheckboxType::class,
                        'int' => NumberType::class,
                        default => assert(false, $type)
                    };

                    $options = [];
                    $settings['propertyType'] = $type;
                    $settings['internalCode'] = $internalCode;

                    if (count($settings)) {
                        $options['attr'] = $settings;
//                        $columnType = json_encode($settings);
//                        $outputHeader .= ':' . $columnType;
                        $outputHeader .= '?' . http_build_query($settings);
//                        dd($outputHeader);
                    }

                    if ($settings['label']??false) {
                        $options['label'] = $settings['label'];
                        unset($settings['label']);
                    }
                    if (count($settings)) {
                        $options['attr'] = $settings;
                        $outputHeader .= '?' . http_build_query($settings);
                    }

                    if ($propertyType == CollectionType::class) {
                        $options['allow_add'] = true;
                    }
                    $formBuilder->add($newColumn, $propertyType, $options);
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
            $csvSchema[$newColumn] = $columnType;
        }

        $form = $formBuilder->getForm();
        // https://github.com/swaggest/php-json-schema -- can we import with this?
        // should validate wth https://github.com/opis/json-schema
        $schema = $this->liform->transform($form);
        dd($schema);
//        foreach ($schema['properties'] as $code => $property) {
//            dump($code, $property);
//        }
//        dd($property, $schema);

        $schemaFilename = 'schema.json';
        file_put_contents($schemaFilename, json_encode($schema, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
//        dd(file_get_contents($schemaFilename));
//        dd($schema, $outputSchema, json_encode($outputSchema, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
        // the input config
        $schema = json_decode(file_get_contents($schemaFilename), true);
        $filterableSortable = [];
        foreach ($schema['properties'] as $code => $property) {
            array_push($filterableSortable, $code);
        }

        $config = [
            'delimiter' => "\t",
            'skipTitle' => true,
            'outputSchema' => $outputSchema,
            // the map, ignores the row headers, so this must be the correct order~
            'schema' => $csvSchema,
            'outputSchemaFilename' => $schemaFilename,
            'valueRules' => [
                '\N' => null
            ],
            'filterableSortable' => $filterableSortable
        ];
        return $config;
    }

}
