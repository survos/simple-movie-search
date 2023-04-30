<?php

namespace App\Command;

use App\Entity\Movie;
use League\Csv\Reader;
use League\Csv\Statement;

use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\GridGroupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\IO;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use function Symfony\Component\String\u;

#[AsCommand('app:create-csv', 'Creates a CSV database from the movie file.  Uses CsvReader from league')]
final class CreateCsvDatabaseCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes, RunsCommands, RunsProcesses;


    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
        string                                                   $name = null)
    {
        parent::__construct($name);
    }

    public function __invoke(
        IO                                                 $io,
        #[Argument(description: 'filename')] string        $filename = 'title.basics.tsv',
        #[Option(description: 'limit')] int                $limit = 10000,
        #[Option(description: 'batch size for flush')] int $batch = 1000,


    ): void
    {
        $slugger = new AsciiSlugger();

        $movieCsv = new CsvDatabase('movie.csv');
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

        // League Readerr
        $csv = Reader::createFromPath($fullFilename, 'r');
        $csv->setDelimiter("\t")
            ->setHeaderOffset(0) // use the first line as header for rows
        ;
        $header = $csv->getHeader();
        var_dump($header);
        $rows = $csv->getRecords();
        foreach ($rows as $row) {
            dump($row);

            $count++;
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
                dump($movie);
            }

            $progressBar->advance();
            if ($limit && ($count > $limit)) {
                break;
            }
        }
        $progressBar->finish();
        $io->success("Done.");
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

}
