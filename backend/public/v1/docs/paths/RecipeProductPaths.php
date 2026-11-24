<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/RecipeProductRoutes.php. RecipeProductRoutesTest checks every status.
class RecipeProductPaths
{
    #[OA\Get(
        path: '/recipes/{id}/products',
        tags: ['recipe products'],
        summary: 'List the products a recipe can make',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Recipe id', example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The links with product code and name', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RecipeProductWithName'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getRecipeProducts() {}

    #[OA\Post(
        path: '/recipes/{id}/products',
        tags: ['recipe products'],
        summary: 'Link a product to a recipe',
        description: "With is_default true the recipe becomes the product's default and the previous default is unset (in one transaction).",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Recipe id', example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeProductInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created link', content: new OA\JsonContent(ref: '#/components/schemas/RecipeProduct')),
            new OA\Response(response: 400, description: 'Invalid data, unknown product or a link that already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postRecipeProducts() {}

    #[OA\Put(
        path: '/recipe-products/{id}',
        tags: ['recipe products'],
        summary: "Make a link the product's default recipe, or unset it",
        description: 'Only is_default can change. true unsets the previous default of the same product (in one transaction).',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeProductUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated link', content: new OA\JsonContent(ref: '#/components/schemas/RecipeProduct')),
            new OA\Response(response: 400, description: 'is_default missing or not true/false', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe product link not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putRecipeProductsById() {}

    #[OA\Delete(
        path: '/recipe-products/{id}',
        tags: ['recipe products'],
        summary: 'Remove a product link',
        description: 'Only the link is removed; the product, the recipe and past runs stay.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 3, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Removed', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe product link not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteRecipeProductsById() {}
}
