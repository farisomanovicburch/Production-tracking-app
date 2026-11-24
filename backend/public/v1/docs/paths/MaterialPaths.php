<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MaterialRoutes.php. MaterialRoutesTest checks every status against the real answers.
class MaterialPaths
{
    #[OA\Get(
        path: '/materials',
        tags: ['materials'],
        summary: 'List all materials with their stock',
        responses: [
            new OA\Response(response: 200, description: 'All materials', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Material'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function getMaterials() {}

    #[OA\Get(
        path: '/materials/{id}',
        tags: ['materials'],
        summary: 'Get one material',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The material', content: new OA\JsonContent(ref: '#/components/schemas/Material')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Material not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMaterialsById() {}

    #[OA\Post(
        path: '/materials',
        tags: ['materials'],
        summary: 'Create a material with its starting stock',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaterialInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created material', content: new OA\JsonContent(ref: '#/components/schemas/Material')),
            new OA\Response(response: 400, description: 'Invalid data or name already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function postMaterials() {}

    #[OA\Put(
        path: '/materials/{id}',
        tags: ['materials'],
        summary: 'Update a material (not its stock)',
        description: 'stock_qty in the body is ignored: stock changes only through deliveries and production runs.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaterialInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated material', content: new OA\JsonContent(ref: '#/components/schemas/Material')),
            new OA\Response(response: 400, description: 'Invalid data or name already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Material not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMaterialsById() {}

    #[OA\Put(
        path: '/materials/{id}/delivery',
        tags: ['materials'],
        summary: 'Record a delivery: add to the stock',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 3, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Delivery')),
        responses: [
            new OA\Response(response: 200, description: 'The material with its new stock', content: new OA\JsonContent(ref: '#/components/schemas/Material')),
            new OA\Response(response: 400, description: 'Missing quantity, or not more than 0', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Material not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMaterialsDelivery() {}

    #[OA\Delete(
        path: '/materials/{id}',
        tags: ['materials'],
        summary: 'Delete a material no recipe or run uses',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 6, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'Used in recipes or runs: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Material not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMaterialsById() {}
}
