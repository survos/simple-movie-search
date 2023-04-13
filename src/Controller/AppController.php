<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Repository\MovieRepository;
use App\Service\CsvCacheAdapter;
use Survos\CoreBundle\Traits\JsonResponseTrait;
use Survos\GridGroupBundle\Service\Reader;
use Survos\GridGroupBundle\Service\CsvCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\MovieSearchType;
use Exception;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Contracts\Cache\ItemInterface;
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

        $csvCache = new CsvCache('new-imdb.csv', 'imdbId', ['primaryTitle','startYear','runtimeMinutes','titleType']);

        $reader = new Reader($fullFilename, strict: false, delimiter: "\t");
        foreach ($reader->getRow() as $idx =>  $row) {
            $imdbId = $row['tconst'];
            $row['imdbId'] = $imdbId;
            if (!$csvCache->contains($imdbId)) {
                $csvCache->set($imdbId, $row);
            } else {
                $data = $csvCache->get($imdbId);

            }
            if ( $idx > $limit) {
                break;
            }
        }
        dd(file_get_contents($csvCache->getDatabase()->getPath()));


    }

    #[Route('/test-cache', name: 'imdb_test_cache')]
    public function test_cache(Request $request,
                           ParameterBagInterface $bag): Response
    {

        $limit = $request->get('limit', 5);
        $filename = 'title.basics.tsv';
        $fullFilename = $this->dataDir . $filename;

        $cache = new CsvCacheAdapter($csvFilename = 'test.csv', 'imdb-cache.csv',  ['primaryTitle','startYear','runtimeMinutes','titleType']);
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
