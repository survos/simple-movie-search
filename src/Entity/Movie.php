<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\MovieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\Api\Filter\JsonSearchFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\Get;
use Survos\ApiGrid\State\MeilliSearchStateProvider;
use Survos\ApiGrid\Filter\MeiliSearch\SortFilter;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(
            normalizationContext: ['movie.read', 'rp', 'searchable']
        )
    ],
    openapiContext:  ["description" => 'Movies and shows in doctrine (postgres)'],
    normalizationContext: ['movie.read', 'rp'],
)]

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/meili',
            provider: MeilliSearchStateProvider::class, # MeilisearchProvider
            normalizationContext: ['movie.read', 'rp', 'searchable']
        )
    ],
    openapiContext:  ["description" => 'meiliseach provider'],
)]
//#[ORM\Index(name: 'movie_imdb_id', columns: ['imdbId'])]
#[ORM\Index(name: 'movie_type', columns: ['type'])]
#[ApiFilter(RangeFilter::class, properties: ['year','runtimeMinutes'])]
#[ApiFilter(SortFilter::class, properties: ['releaseName', 'year', 'primaryTitle','runtimeMinutes'], arguments: ['orderParameterName' => 'sort'])]
#[ApiFilter(OrderFilter::class, properties: ['releaseName', 'year', 'primaryTitle','runtimeMinutes'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['releaseName', 'imdbId'])] # Mei?isearch
// can we move this to a property
#[ApiFilter(JsonSearchFilter::class, properties: ['attributes'], arguments: ['searchParameterName' => 'attribute_search'])]
// This is a doctrine facet filter that works when you select any fields from left seachPanes
//#[ApiFilter(FacetsFieldSearchFilter::class, properties: ["imdbId","releaseName","title"], arguments: [
//    "searchParameterName" => "facet_filter",
//])]
class Movie
{
    #[ORM\Column]
    #[ORM\Id]
    #[ApiProperty(identifier: true)]
    #[Groups(['searchable','movie.read'])]
    private int $imdbId;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['searchable','movie.read'])]
    private ?string $releaseName = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['searchable','movie.read'])]
    private ?int $year = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    #[Groups(['searchable','movie.read'])]
    private ?string $primaryTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['searchable','movie.read'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    #[ApiProperty(openapiContext: ["type" => "string", "description" => 'Movie type', "example" => "short"])]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    #[Groups(['searchable','movie.read'])]
    private ?bool $adult = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['searchable','movie.read'])]
    private ?int $runtimeMinutes = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    #[Groups(['searchable','movie.read'])]
    private array $genres = [];


    public function getImdbId(): int
    {
        return $this->imdbId;
    }

    public function setImdbId(int $imdbId): self
    {
        $this->imdbId = $imdbId;

        return $this;
    }

    public function getReleaseName(): ?string
    {
        return $this->releaseName;
    }

    public function setReleaseName(?string $releaseName): self
    {
        $this->releaseName = $releaseName;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getPrimaryTitle(): ?string
    {
        return $this->primaryTitle;
    }

    public function setPrimaryTitle(?string $primaryTitle): self
    {
        $this->primaryTitle = $primaryTitle;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isAdult(): ?bool
    {
        return $this->adult;
    }

    public function setAdult(?bool $adult): self
    {
        $this->adult = $adult;

        return $this;
    }

    public function getRuntimeMinutes(): ?int
    {
        return $this->runtimeMinutes;
    }

    public function setRuntimeMinutes(?int $runtimeMinutes): self
    {
        $this->runtimeMinutes = $runtimeMinutes;

        return $this;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function setGenres(?array $genres): self
    {
        $this->genres = $genres;

        return $this;
    }
}
