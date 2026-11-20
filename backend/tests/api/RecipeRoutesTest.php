<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in RecipeServiceTest.
final class RecipeRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Recipe not found.'];

    private function createRecipeThroughTheApi(string $name): array
    {
        return $this->request('POST', '/backend/recipes', ['name' => $name, 'notes' => 'Route test']);
    }

    public function testListRecipes(): void
    {
        $response = $this->request('GET', '/backend/recipes');

        $this->assertSame(200, $response['status']);
        $this->assertContains('Clear film', array_column($response['body'], 'name'));
        $this->assertDocumented('GET', '/recipes', 200);
    }

    public function testGetOneRecipe(): void
    {
        $response = $this->request('GET', '/backend/recipes/2');

        $this->assertSame(200, $response['status']);
        $this->assertSame('White film', $response['body']['name']);
        $this->assertDocumented('GET', '/recipes/{id}', 200);
    }

    public function testGetAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/recipes/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/recipes/{id}', 404);
    }

    public function testCreateARecipeAnswers201(): void
    {
        $response = $this->createRecipeThroughTheApi('Route create recipe');

        $this->assertSame(201, $response['status']);
        $this->assertSame('Route test', $response['body']['notes']);
        $this->assertDocumented('POST', '/recipes', 201);
    }

    public function testCreateARecipeWithADuplicateNameIsABadRequest(): void
    {
        $response = $this->createRecipeThroughTheApi('Clear film');

        $this->assertSame(['status' => 400, 'error' => 'A recipe with this name already exists.'], $response['body']);
        $this->assertDocumented('POST', '/recipes', 400);
    }

    public function testUpdateARecipe(): void
    {
        $id = $this->createRecipeThroughTheApi('Route update recipe')['body']['id'];

        $response = $this->request('PUT', "/backend/recipes/$id", ['name' => 'Route renamed recipe']);

        $this->assertSame(200, $response['status']);
        $this->assertSame('Route renamed recipe', $response['body']['name']);
        $this->assertDocumented('PUT', '/recipes/{id}', 200);
    }

    public function testUpdateARecipeWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/recipes/2', ['name' => 'Clear film']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/recipes/{id}', 400);
    }

    public function testUpdateAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/recipes/999999', ['name' => 'Nothing']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/recipes/{id}', 404);
    }

    public function testDeleteARecipe(): void
    {
        $id = $this->createRecipeThroughTheApi('Route delete recipe')['body']['id'];

        $response = $this->request('DELETE', "/backend/recipes/$id");

        $this->assertSame(['message' => 'Recipe deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/recipes/$id")['status']);
        $this->assertDocumented('DELETE', '/recipes/{id}', 200);
    }

    public function testDeleteARecipeWithRunsIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/recipes/1');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate it instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/recipes/{id}', 400);
    }

    public function testDeleteAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/recipes/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/recipes/{id}', 404);
    }
}
