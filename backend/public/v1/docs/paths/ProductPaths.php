<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/ProductRoutes.php. ProductRoutesTest checks every status against the real answers.
class ProductPaths
{
    #[OA\Get(
        path: '/products',
        tags: ['products'],
        summary: 'List all products',
        responses: [
            new OA\Response(response: 200, description: 'All products', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')))
        ]
    )]
    public function getProducts() {}

    #[OA\Get(
        path: '/products/{id}',
        tags: ['products'],
        summary: 'Get one product',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The product', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getProductsById() {}

    #[OA\Post(
        path: '/products',
        tags: ['products'],
        summary: 'Create a product',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created product', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 400, description: 'Invalid data or code already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postProducts() {}

    #[OA\Put(
        path: '/products/{id}',
        tags: ['products'],
        summary: 'Update a product',
        description: 'Send an empty value to clear an optional field. Set is_active to false to stop new runs from using the product.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated product', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 400, description: 'Invalid data or code already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putProductsById() {}

    #[OA\Delete(
        path: '/products/{id}',
        tags: ['products'],
        summary: 'Delete a product no run or conversion has used',
        description: 'Its machine and recipe links are deleted with it.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 5, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'The product has runs or conversions: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteProductsById() {}
}
