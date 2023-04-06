<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

class AppController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    #[Route('/_search', name: 'app_search', options: ['expose' => true])]
    public function index(SearchService $searchService, EntityManagerInterface $em, Request $request): Response
    {

        $searchQuery = $request->get('q') ?? '';
        $filter = $request->get('filters')?? "";
        $filterData = [];
        $rawFilter = json_decode($filter,true);

        $startYear = (int)$request->get('startYear');
        $endYear = (int)$request->get('endYear');

        // Filters
         $movies = $searchService->search($em, Movie::class, $searchQuery, ['filter' => "year > $startYear AND year < $endYear",'sort' => ['year:asc']]);
         dd($searchQuery, $startYear, $endYear, $movies);
        // sort
//        $movies = $searchService->search($em, Movie::class, $searchQuery, ['sort' => ['year:desc']]);

        return $this->render($request->get('_route') == 'app_search' ?  'app/_movies.html.twig': 'app/index.html.twig', [
            'movies' => $movies
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

    #[Route('/show/{imdbId}', name: 'movie_show', options: ['expose' => true])]
    public function show(int $imdbId, MovieRepository $movieRepository): Response
    {
        $movie = $movieRepository->findOneBy(['imdbId' => $imdbId]);
        return $this->render('app/movie.html.twig', [
            'movie' => $movie
        ]);
    }

}
