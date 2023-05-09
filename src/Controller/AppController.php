<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Repository\MovieRepository;
use League\Csv\Reader;
use Limenius\Liform\Liform;
use Limenius\Liform\LiformInterface;
use Psr\Cache\CacheItemInterface;
use Survos\GridGroupBundle\CsvSchema\Parser;
use Survos\GridGroupBundle\Service\CsvCache;
use Survos\CoreBundle\Traits\JsonResponseTrait;
use Survos\GridGroupBundle\Service\CsvCacheAdapter;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\Scraper\Service\LocalWebpageCacheAdapter;
use Survos\Scraper\Service\ScraperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\MovieSearchType;
use Exception;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\ItemInterface;
use function PHPUnit\Framework\assertEquals;
use function Symfony\Component\String\u;

class AppController extends AbstractController
{
    use JsonResponseTrait;
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
    ) {

    }
    #[Route('/', name: 'app_homepage')]
    public function index(SearchService $searchService, EntityManagerInterface $em, Request $request): Response
    {
        return $this->render('app/homepage.html.twig', [
            'class' => Movie::class,
        ]);

    }
    #[Route('/test-parser', name: 'test_parser')]
    public function test_parser( Request $request): Response|iterable
    {
        $csvString = "name,age
bob,44";
        $csvReader = Reader::createFromString($csvString)->setHeaderOffset(0);
        var_dump($csvReader->first());
        dd('stopped');

        // this should be done by a php unit test
        $yaml = Yaml::parseFile($this->dataDir . '../tests/parser-test.yaml');
        foreach ($yaml['tests'] as $test) {
            $csvString = $test['source'];
            $csvReader = Reader::createFromString($csvString)->setHeaderOffset(0);
            $schema = Parser::createSchemaFromMap($test['map']??[], $csvReader->getHeader());
//                dd($schema, $map, $csvReader->getHeader());
            $config['schema'] = $schema;
            $parser = new Parser($config);
            foreach ($parser->fromString($csvString) as $row) {
                $expects = json_decode($test['expects'], true);
                dump($csvString, $test['expects']);
                assert($expects, "invalid json: " . $test['expects']);
                assertEquals($expects, $row, json_encode($expects) . '<>' . json_encode($row));
            }
        }
        dd('all tests pass');
        return [];
    }

    #[Route('/import_with_parser', name: 'import_with_parser')]
    public function import_with_parser(SearchService $searchService, EntityManagerInterface $em, Request $request): Response|iterable
    {
        $filename = $this->dataDir . 'title.small.tsv';
        $csv = \League\Csv\Reader::createFromPath($filename)->setDelimiter("\t");
        $csv->setHeaderOffset(0);
        foreach ($csv->getHeader() as $header) {
            $schema[$header] = 'string';
        }

        $map = Yaml::parse(<<<END
map:
    /tconst/i: id:string
    /primary_title/i: title:string
    /year/i: int?max=2024
    /runtime_in_minutes/i: runtime:int?units=min
    /type/i: type:string
    /adult/i: adult:bool?permission=admin
    /genre/i: array,
END
        );


        $config = [
            'delimiter' => "\t",
            'skipTitle' => true,
            'valueRules' => [
                '\N' => null
            ],
            'schema' => Parser::createSchemaFromMap($map, $csv->getHeader())
        ];
        $parser = new Parser($config);
        $rows = $parser->fromFile($filename);
        foreach ($rows as $row) {
            dd($row);
        }

        $header_offset = $csv->getHeaderOffset(); //returns 0
        $header = $csv->getHeader();
        $rows = [];
        foreach ($csv->getIterator() as $row) {
            $rows[] = $row;
            dd($row);
        }

        // age should be an integer.
        return $rows;

    }



#[Route('/_search', name: 'app_search', options: ['expose' => true])]
    public function search(SearchService $searchService, EntityManagerInterface $em, Request $request): Response
    {

        $formData = $request->get('movie_search') ? $request->get('movie_search') : [];

        $searchQuery = isset($formData['search']) ? $formData['search'] :  '';
        $fromYear = isset($formData['from_year']) ? $formData['from_year'] : false;
        $toYear = isset($formData['to_year']) ? $formData['to_year'] : false;
        $sortby = isset($formData['sortby']) ? $formData['sortby'] : 'year';
        $direction = isset($formData['direction']) ? $formData['direction'] : 'asc';
        $type = isset($formData['type']) ? $formData['type'] : "";
        $filter = $fromYear ? 'year > '.$fromYear:"";
        $filter = $toYear ? $filter != "" ? $filter."  AND year < ". $toYear: $filter."year < ". $toYear:$filter;
//        $filter = $type != "" ? $filter != ""? $filter." AND type =".$type : " type = ".$type: $filter;

            $movies = $searchService->rawSearch(Movie::class, $searchQuery, [
                'filter' => $filter,
                'sort' => [$sortby.':'.$direction],
                'facets' => ['year', 'type']
            ]);
        try {
        } catch(\Exception $e) {
            throw new Exception("Somthing went wrong with Search");
        }

        $form = $this->createForm(MovieSearchType::class, null, [
            'method' => 'GET',
            "facets" => $movies['facetDistribution'],
            "default_values" => [
                'search' => $searchQuery,
                'from' => $fromYear,
                'to' => $toYear,
                'type' => $type,
                'sortby' => $sortby,
                'direction' => $direction
                ]
        ]);

        return $this->render('app/index.html.twig',[
                'movies' => $movies,
                'form' => $form
        ]);
    }

    #[Route('/test-url-cache', name: 'app_url_cache')]
    public function url_cache(Request $request, ScraperService $scraperService): Response
    {
        $scraperService->setDir('../data/museum-digital-cache');
        $limit = $request->get('limit', 5);

        for ($i = 0; $i < $limit;  $i++) {
            $url = 'https://global.museum-digital.org/json/objects?style=liste&s=metadata_rights%3ACC0&style=liste&startwert=' . ($i * 20);

            $filename = $scraperService->fetchUrlFilename($url, [], ['Content-Type' => 'application/json'], key: $key=sprintf('cc0-page-%s.json', $i));
            $json = file_get_contents($filename);
            $data = json_decode($json, true);
        }

        // with adapter
        $urlCacheAdapter = (new LocalWebpageCacheAdapter($dir));
        $path = $urlCacheAdapter->get($key, function (CacheItemInterface $cacheItem) {

        });


        return $this->redirect(); // add template, etc. for debugging
    }



    #[Route('/browse', name: 'app_browse')]
    public function browse(Request $request): Response
    {
        $filter = [

        ];
        return $this->render('app/browse.html.twig', [
            'class' => Movie::class,
            'filter' => $filter,
        ]);
    }

    #[Route('/browse_dynamic', name: 'app_browse_dynamic')]
    public function browse_dynamic(Request $request): Response
    {
        $projectRoot = $this->getParameter('kernel.project_dir');
        $schema = json_decode(file_get_contents($schemaFilename = $projectRoot . '/schema.json'));
        // use inspection bundle to get data about the

        $columns = [];
        foreach ($schema->properties as $propertyName => $property) {
            $attr = $property->attr;
            $browsable = (in_array($property->type, ['bool', 'string'])) && ($attr->propertyType <> 'db');
            $searchable = (in_array($property->type, ['string','int']));
            $columns[] = [
                'searchable' => $searchable,
                'name' => $propertyName,
                'browsable' => $browsable,
            ];
        }
//        dd($columns, $schema->properties, $schemaFilename);

//        dd($schema);


        $filter = [

        ];
        return $this->render('app/browse_dynamic.html.twig', [
            'schema' => $schema,
            'class' => Movie::class,
            'columns' => $columns,
            'filter' => $filter,
        ]);
    }

    #[Route(path: '/fieldCounts.{_format}', name: 'movie_field_counts', methods: ['POST', 'GET'])]
    public function field_counts(Request    $request,
                                            MovieRepository $movieRepository,
                                            $_format = 'json'

    ): Response
    {


        $results = [];
        foreach (['type'] as $field) {
            foreach ($movieRepository->getCounts($field) as $valueName=>$count) {
                $r = [
                    'label' => $valueName,
                    'count' => $count,
                    'total' => $count, // wrong!
                    'value' => $valueName
                ];
                $results[$field][] = $r;
            }
        }
        return $this->jsonResponse($results, $request, $_format);


        return $this->render('app/test.html.twig', ['result' => $result]);
    }

    #[Route('/meili', name: 'app_browse_meili')]
    public function meili(Request $request): Response
    {
        $filter = [

        ];
        return $this->render('app/meili.html.twig', [
            'class' => Movie::class,
            'filter' => $filter,
        ]);
    }

    #[Route('/show/{imdbId}', name: 'movie_show', options: ['expose' => true])]
    public function show(int $imdbId, MovieRepository $movieRepository): Response
    {
        $movie = $movieRepository->findOneBy(['imdbId' => $imdbId]);
        return $this->render('app/movie.html.twig', [
            'movie' => $movie
        ]);
    }

    #[Route('/import', name: 'imdb_import')]
    public function import(Request $request,
                           ParameterBagInterface $bag): Response
    {
        $limit = $request->get('limit', 5);
        $filename = 'title.basics.tsv';
        $fullFilename = $this->dataDir . $filename;
        $count = 0;

        $csvDatabase = new CsvDatabase('new-imdb.csv', 'imdbId', ['primaryTitle','startYear','runtimeMinutes','titleType']);

        $reader = new Reader($fullFilename, strict: false, delimiter: "\t");
        foreach ($reader->getRow() as $idx =>  $row) {
            $imdbId = $row['tconst'];
            $row['imdbId'] = $imdbId;
            if (!$csvDatabase->has($imdbId)) {
                $csvDatabase->set($imdbId, $row);
            } else {
                $data = $csvDatabase->get($imdbId);

            }
            if ( $idx > $limit) {
                break;
            }
        }
        dd(file_get_contents($csvDatabase->getPath()));


    }

        #[Route('/test-cache', name: 'imdb_test_cache')]
    public function test_cache(Request $request,
                               ParameterBagInterface $bag): Response
    {
        $limit = $request->get('limit', 5);
        $filename = 'title.basics.tsv';
        $fullFilename = $this->dataDir . $filename;

        $map = [

        ];

        $config = [
            'schema' => [
                'first_name' => 'string',
                'last_name' => 'string',
                'age' => 'int',
                'coolness_factor' => 'float',
            ]
        ];
        $parser = new Parser($config);
        $input = "Kai,Sassnowski,26,0.3\nJohn,Doe,38,7.8";
        $rows = $parser->fromString($input);

        foreach ($rows as $row) {
            dump($row);
        }


        $cache = new CsvCacheAdapter($csvFilename = 'test.csv', 'tconst',  ['primaryTitle','startYear','runtimeMinutes','titleType']);
        $reader = new Reader($fullFilename, strict: false, delimiter: "\t");
        foreach ($reader->getRow() as $idx =>  $row) {
            $imdbId = $row['tconst'];
            $x = $cache->get($imdbId, function (ItemInterface $item) use ($row) {
//                $reviews = $movieService->downloadReviews($imdbId)
//                return $reviews;
                return $row;
            });
            dump($x);

            if ( $idx > $limit) {
                break;
            }
        }
        dd('stopped, see ' . $csvFilename);


    }

}
