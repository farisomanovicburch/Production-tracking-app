<?php
use OpenApi\Attributes as OA;

/*
 * Documentation of the routes in backend/index.php. One empty method per endpoint: it only carries the attribute,
 * because swagger-php cannot read attributes from the closures passed to Flight::route().
 */
class ApiPaths
{
    #[OA\Get(
        path: '/',
        tags: ['api'],
        summary: 'Check that the backend is running',
        responses: [
            new OA\Response(
                response: 200,
                description: 'The backend is running',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'app', type: 'string', example: 'Production Tracker API'),
                        new OA\Property(property: 'message', type: 'string', example: 'The backend is running.'),
                        new OA\Property(property: 'docs', type: 'string', example: 'public/v1/docs/')
                    ]
                )
            )
        ]
    )]
    public function getRoot() {}
}
