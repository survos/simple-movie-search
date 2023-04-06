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
use App\Form\MovieSearchType;
use Exception;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;

class AppController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    #[Route('/_search', name: 'app_search', options: ['expose' => true])]
    public function index(SearchService $searchService, EntityManagerInterface $em, Request $request): Response
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
        $filter = $type != "" ? $filter != ""? $filter." AND type =".$type : " type = ".$type: $filter;
        
        try {
            $movies = $searchService->rawSearch(Movie::class, $searchQuery, [
                'filter' => $filter,
                'sort' => [$sortby.':'.$direction],
                'facets' => ['year', 'type']
            ]);    
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

    #[Route('/show/{imdbId}', name: 'movie_show', options: ['expose' => true])]
    public function show(int $imdbId, MovieRepository $movieRepository): Response
    {
        $movie = $movieRepository->findOneBy(['imdbId' => $imdbId]);
        return $this->render('app/movie.html.twig', [
            'movie' => $movie
        ]);
    }

}
