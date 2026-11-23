<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in RecipeItemServiceTest.
final class RecipeItemRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Recipe item not found.'];

    // A recipe of its own, so the 100 % a test uses never collides with another test's or the sample data.
    private function createItem(string $recipeName): array
    {
        $recipeId = $this->request('POST', '/backend/recipes', ['name' => $recipeName])['body']['id'];
        return $this->request('POST', "/backend/recipes/$recipeId/items", ['material_id' => 2, 'percentage' => 80]);
    }

    public function testListTheItemsOfARecipe(): void
    {
        $response = $this->request('GET', '/backend/recipes/1/items');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['LDPE granulate', 'Slip additive'], array_column($response['body'], 'material_name'));
        $this->assertDocumented('GET', '/recipes/{id}/items', 200);
    }

    public function testListTheItemsOfAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/recipes/999999/items');

        $this->assertSame(['status' => 404, 'error' => 'Recipe not found.'], $response['body']);
        $this->assertDocumented('GET', '/recipes/{id}/items', 404);
    }

    public function testAddAnItemAnswers201(): void
    {
        $response = $this->createItem('Route add item recipe');

        $this->assertSame(201, $response['status']);
        $this->assertSame('80.00', $response['body']['percentage']);
        $this->assertDocumented('POST', '/recipes/{id}/items', 201);
    }

    // Recipe 1 already adds up to 100 %.
    public function testAddAnItemAboveAHundredPercentIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/recipes/1/items', ['material_id' => 2, 'percentage' => 1]);

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('more than 100 %', $response['body']['error']);
        $this->assertDocumented('POST', '/recipes/{id}/items', 400);
    }

    public function testAddAnItemToAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/recipes/999999/items', ['material_id' => 2, 'percentage' => 10]);

        $this->assertSame(['status' => 404, 'error' => 'Recipe not found.'], $response['body']);
        $this->assertDocumented('POST', '/recipes/{id}/items', 404);
    }

    public function testUpdateAnItem(): void
    {
        $id = $this->createItem('Route update item recipe')['body']['id'];

        $response = $this->request('PUT', "/backend/recipe-items/$id", ['material_id' => 2, 'percentage' => 100]);

        $this->assertSame(200, $response['status']);
        $this->assertSame('100.00', $response['body']['percentage']);
        $this->assertDocumented('PUT', '/recipe-items/{id}', 200);
    }

    public function testUpdateAnItemWithInvalidDataIsABadRequest(): void
    {
        $id = $this->createItem('Route invalid item recipe')['body']['id'];

        $response = $this->request('PUT', "/backend/recipe-items/$id", ['material_id' => 2, 'percentage' => 0]);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/recipe-items/{id}', 400);
    }

    public function testUpdateAnUnknownItemIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/recipe-items/999999', ['material_id' => 2, 'percentage' => 10]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/recipe-items/{id}', 404);
    }

    public function testDeleteAnItem(): void
    {
        $id = $this->createItem('Route delete item recipe')['body']['id'];

        $response = $this->request('DELETE', "/backend/recipe-items/$id");

        $this->assertSame(['message' => 'Recipe item deleted.'], $response['body']);
        $this->assertDocumented('DELETE', '/recipe-items/{id}', 200);
    }

    public function testDeleteAnUnknownItemIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/recipe-items/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/recipe-items/{id}', 404);
    }
}
