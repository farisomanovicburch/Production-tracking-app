<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MachineProductRoutes.php. MachineProductRoutesTest checks every status.
class MachineProductPaths
{
    #[OA\Get(
        path: '/machines/{id}/products',
        tags: ['machine products'],
        summary: 'List the products a machine takes in and makes',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Machine id', example: 3, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The links with product code and name', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/MachineProductWithName'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMachineProducts() {}

    #[OA\Post(
        path: '/machines/{id}/products',
        tags: ['machine products'],
        summary: 'Link a product to a machine as input or output',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Machine id', example: 3, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MachineProductInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created link', content: new OA\JsonContent(ref: '#/components/schemas/MachineProduct')),
            new OA\Response(response: 400, description: 'Invalid data, unknown product, an input on an extruder or a link that already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postMachineProducts() {}

    #[OA\Delete(
        path: '/machine-products/{id}',
        tags: ['machine products'],
        summary: 'Remove a product link',
        description: 'Only the link is removed; the product and past runs stay.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 10, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Removed', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Machine product link not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMachineProductsById() {}
}
