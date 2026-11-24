<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/RecipeRoutes.php. RecipeRoutesTest checks every status against the real answers.
class RecipePaths
{
    #[OA\Get(
        path: '/recipes',
        tags: ['recipes'],
        summary: 'List all recipes',
        responses: [
            new OA\Response(response: 200, description: 'All recipes', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Recipe'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function getRecipes() {}

    #[OA\Get(
        path: '/recipes/{id}',
        tags: ['recipes'],
        summary: 'Get one recipe',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The recipe', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getRecipesById() {}

    #[OA\Post(
        path: '/recipes',
        tags: ['recipes'],
        summary: 'Create a recipe (add its materials afterwards)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created recipe', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 400, description: 'Invalid data or name already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function postRecipes() {}

    #[OA\Put(
        path: '/recipes/{id}',
        tags: ['recipes'],
        summary: 'Update a recipe',
        description: 'Set is_active to false to stop new runs from using the recipe.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RecipeInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated recipe', content: new OA\JsonContent(ref: '#/components/schemas/Recipe')),
            new OA\Response(response: 400, description: 'Invalid data or name already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putRecipesById() {}

    #[OA\Delete(
        path: '/recipes/{id}',
        tags: ['recipes'],
        summary: 'Delete a recipe no run has used',
        description: 'Its items and product links are deleted with it.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 4, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'The recipe has production runs: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Recipe not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteRecipesById() {}
}
