<?php
use OpenApi\Attributes as OA;

/*
 * General information about the API and the schemas shared by many endpoints (referenced from paths/ with
 * ref: '#/components/schemas/...'). The attributes need a class to sit on, so the class itself is empty.
 * Plain block comments only: swagger-php would turn a doc comment into a description.
 */
#[OA\Info(
    title: 'Production Tracker API',
    description: 'REST API of Production Tracker: production runs, conversions, maintenance and material stock ' .
        'of a plastic film and bag factory. IT 2001 Web Programming course project.',
    version: '1.0'
)]
#[OA\Server(url: API_BASE_URL, description: 'This server')]
class OpenApiSpec {}
