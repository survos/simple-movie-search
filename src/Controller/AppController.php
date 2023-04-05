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
    public function index(SearchService $searchService, EntityManagerInterface $em, Request $request): Response
    {
        $searchQuery = $request->query->get('q') ?? '';
        $filter = $request->query->get('filters')?? "";
        $filterData = [];
        $rawFilter = json_decode($filter,true);

        // Filters
        // $movies = $searchService->search($em, Movie::class, $searchQuery, ['filter' => 'year > 1894 AND year < 1896','sort' => ['year:asc']]);
        // sort
        $movies = $searchService->search($em, Movie::class, $searchQuery, ['sort' => ['year:desc']]);

        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
            'movies' => $movies
        ]);
    }

    #[Route('/browse', name: 'app_browse')]
    public function browse(): Response
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
