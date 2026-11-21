<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MaterialUsageRoutes.php. MaterialUsageRoutesTest checks every status.
class MaterialUsagePaths
{
    #[OA\Get(
        path: '/production-runs/{id}/material-usages',
        tags: ['material usages'],
        summary: "List a run's material usages, largest first",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Production run id', example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The usages with material names', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/MaterialUsageWithName'))),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMaterialUsages() {}

    #[OA\Post(
        path: '/production-runs/{id}/material-usages',
        tags: ['material usages'],
        summary: 'Add a usage to an existing run (takes it from stock)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Production run id', example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaterialUsageInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created usage', content: new OA\JsonContent(ref: '#/components/schemas/MaterialUsage')),
            new OA\Response(response: 400, description: 'Invalid data, unknown material, not in the recipe, already used, or not enough stock', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postMaterialUsages() {}

    #[OA\Put(
        path: '/material-usages/{id}',
        tags: ['material usages'],
        summary: 'Correct the amount (the stock moves by the difference)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaterialUsageUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated usage', content: new OA\JsonContent(ref: '#/components/schemas/MaterialUsage')),
            new OA\Response(response: 400, description: 'Invalid amount, or not enough stock for the increase', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Material usage not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMaterialUsagesById() {}

    #[OA\Delete(
        path: '/material-usages/{id}',
        tags: ['material usages'],
        summary: 'Delete a usage (gives the material back to stock)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 11, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Material usage not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMaterialUsagesById() {}
}
