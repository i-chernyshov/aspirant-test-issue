<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Movie;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Interfaces\RouteCollectorInterface;
use Twig\Environment;

class MovieController
{
    public function __construct(
        private RouteCollectorInterface $routeCollector,
        private Environment $twig,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * @throws HttpBadRequestException
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->twig->render('movie/index.html.twig', [
                'movies' => new ArrayCollection($this->em->getRepository(Movie::class)->findAll()),
                'router' => $this->routeCollector->getRouteParser(),
            ]);
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }

    /**
     * @throws HttpBadRequestException
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        try {
            $data = $this->twig->render('movie/show.html.twig', [
                'movie' => $this->em->getRepository(Movie::class)->find($params['id']),
                'router' => $this->routeCollector->getRouteParser(),
            ]);
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }
}
