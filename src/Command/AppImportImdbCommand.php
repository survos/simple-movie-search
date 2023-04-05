<?php

namespace App\Command;

use App\Entity\Movie;
use App\Repository\MovieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Survos\GridGroupBundle\Service\GridGroupService;
use Survos\GridGroupBundle\Service\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\IO;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use function Symfony\Component\String\u;

#[AsCommand('app:import-imdb', '')]
final class AppImportImdbCommand extends InvokableServiceCommand
{
    use ConfigureWithAttributes, RunsCommands, RunsProcesses;


    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
        private EntityManagerInterface                           $ostEntityManager,
        private MovieRepository                                  $movieRepository,
        string                                                   $name = null)
    {
        parent::__construct($name);
    }

    public function __invoke(
        IO                                          $io,
        #[Argument(description: 'filename')] string $filename = 'title.basics.tsv',
        #[Option(description: 'limit')] int         $limit = 10,


    ): void
    {

        $ostEntityManager = $this->ostEntityManager;

        $fullFilename = $this->dataDir . $filename;
        $process = (new Process(['wc', '-l', $fullFilename]));
        $process->run();
        $output = $process->getOutput();
        $lineCount = (int)u($output)->before(' ')->toString();

        $io->warning("Loading " . $fullFilename . " get $limit of $lineCount");
        $progressBar = new ProgressBar($io->output(), $lineCount);
        $movieRepository = $ostEntityManager->getRepository(Movie::class);

        $count = 0;

        $reader = new Reader($fullFilename);
        foreach ($reader->getRow() as $row) {
            dd($row);

        }

        $reader = new \EasyCSV\Reader($fullFilename);
        while ($row = $reader->getRow()) {
            dd($row);
        }

        foreach (GridGroupService::fetchRow($fullFilename, separator: "\t") as $row) {
            $count++;
            $imdbId = (int)u($row['tconst'])->after('tt')->toString();
            if (!$movie = $movieRepository->findOneBy(['imdbId' => $imdbId])) {
                $movie = (new Movie())->setImdbId($imdbId);
                $ostEntityManager->persist($movie);
            }
            foreach ($row as $key => $value) {
                if ($value == '\N') {
                    $row[$key] = null;
                }
            }
            $movie
//                ->setMovieType('movie')
                ->setPrimaryTitle($row['primaryTitle'])
                ->setReleaseName($row['originalTitle'])
                ->setType($row['titleType'])
                ->setAdult((bool)$row['isAdult'])
                ->setRuntimeMinutes($row['runtimeMinutes'])
                ->setYear((int)$row['startYear']);
            $progressBar->setMessage($movie->getReleaseName());
            $progressBar->advance();
            if ($limit && ($count > $limit)) {
                break;
            }

        }
        $progressBar->finish();
        $io->success("Flushing..." . $count);
        $ostEntityManager->flush();
        $io->success("Done.");
    }

}
