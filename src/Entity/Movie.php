<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\MovieRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ApiResource(
    openapiContext:  ["description" => 'Movies and shows, keyed by IMDB id', "example" => "Rainman"],
    normalizationContext: ['movie.read', 'rp']
)]
//#[ORM\Index(name: 'movie_imdb_id', columns: ['imdbId'])]
#[ORM\Index(name: 'movie_type', columns: ['type'])]
#[ApiFilter(RangeFilter::class, properties: ['year','runtimeMinutes'])]
#[ApiFilter(OrderFilter::class, properties: ['releaseName', 'year', 'primaryTitle','runtimeMinutes'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['releaseName', 'imdbId'])] # Mei?isearch


class Movie
{
    #[ORM\Column]
    #[ORM\Id]
    #[ApiProperty(identifier: true)]
    private int $imdbId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $releaseName = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['searchable','movie.read'])]
    private ?int $year = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    private ?string $primaryTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    #[ApiProperty(openapiContext: ["type" => "string", "description" => 'Movie type', "example" => "short"])]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?bool $adult = null;

    #[ORM\Column(nullable: true)]
    private ?int $runtimeMinutes = null;


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
}
