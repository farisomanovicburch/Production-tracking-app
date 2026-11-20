<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/RecipeItemRoutes.php. RecipeItemRoutesTest checks every status.
class RecipeItemPaths
{
    #[OA\Get(
        path: '/recipes/{id}/items',
        tags: ['recipe items'],
        summary: "List a recipe's materials, largest share first",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Recipe id', example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The items with material names', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RecipeItemWithName'))),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getRecipeItems() {}

    #[OA\Post(
        path: '/recipes/{id}/items',
        tags: ['recipe items'],
        summary: 'Add a material to a recipe',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Recipe id', example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeItemInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created item', content: new OA\JsonContent(ref: '#/components/schemas/RecipeItem')),
            new OA\Response(response: 400, description: 'Invalid data, unknown material, material already in the recipe, or more than 100 % in total', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postRecipeItems() {}

    #[OA\Put(
        path: '/recipe-items/{id}',
        tags: ['recipe items'],
        summary: 'Change the material or share of an item',
        description: 'The new share replaces the old one in the 100 % check. The item stays in its recipe.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 3, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeItemInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated item', content: new OA\JsonContent(ref: '#/components/schemas/RecipeItem')),
            new OA\Response(response: 400, description: 'Invalid data, material already in the recipe, or more than 100 % in total', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Recipe item not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putRecipeItemsById() {}

    #[OA\Delete(
        path: '/recipe-items/{id}',
        tags: ['recipe items'],
        summary: 'Remove a material from a recipe',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 9, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Recipe item not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteRecipeItemsById() {}
}
