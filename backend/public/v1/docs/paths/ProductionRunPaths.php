<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/ProductionRunRoutes.php. ProductionRunRoutesTest checks every status.
class ProductionRunPaths
{
    #[OA\Get(
        path: '/production-runs',
        tags: ['production runs'],
        summary: 'List all runs, newest first (without values and usages)',
        responses: [
            new OA\Response(response: 200, description: 'All runs with machine, product, recipe and user names', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductionRun'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function getProductionRuns() {}

    #[OA\Get(
        path: '/production-runs/{id}',
        tags: ['production runs'],
        summary: 'Get one run with its parameter values and material usages',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The run', content: new OA\JsonContent(ref: '#/components/schemas/ProductionRunWithDetails')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getProductionRunsById() {}

    #[OA\Post(
        path: '/production-runs',
        tags: ['production runs'],
        summary: 'Log a finished run with its parameter values and material usages',
        description: 'Saved in one transaction together with the stock decrease: if any rule fails (e.g. not enough stock), nothing is saved.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductionRunInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created run', content: new OA\JsonContent(ref: '#/components/schemas/ProductionRunWithDetails')),
            new OA\Response(
                response: 400,
                description: 'Invalid data, wrong time order, a machine/product/recipe/user that does not fit or is deactivated, missing parameter values, a material outside the recipe, or not enough stock',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function postProductionRuns() {}

    #[OA\Put(
        path: '/production-runs/{id}',
        tags: ['production runs'],
        summary: "Correct a run's times, amounts or notes",
        description: 'machine_id, product_id, recipe_id and user_id are ignored (locked after create). Values and usages have their own routes.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductionRunUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated run', content: new OA\JsonContent(ref: '#/components/schemas/ProductionRunWithDetails')),
            new OA\Response(response: 400, description: 'Invalid data or wrong time order', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putProductionRunsById() {}

    #[OA\Delete(
        path: '/production-runs/{id}',
        tags: ['production runs'],
        summary: 'Delete a run; its material goes back into stock',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 5, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteProductionRunsById() {}
}
