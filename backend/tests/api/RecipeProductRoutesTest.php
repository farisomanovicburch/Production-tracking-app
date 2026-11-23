<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in RecipeProductServiceTest.
final class RecipeProductRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Recipe product link not found.'];

    // A product of its own, so changing its default never touches the sample data's defaults.
    private function createLink(string $code): array
    {
        $recipeId = $this->request('POST', '/backend/recipes', ['name' => "Route recipe $code"])['body']['id'];
        $productId = $this->request('POST', '/backend/products', ['code' => $code, 'name' => 'Route test film'])['body']['id'];
        return $this->request('POST', "/backend/recipes/$recipeId/products", ['product_id' => $productId]);
    }

    public function testListTheProductsOfARecipe(): void
    {
        $response = $this->request('GET', '/backend/recipes/1/products');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['P-100'], array_column($response['body'], 'product_code'));
        $this->assertDocumented('GET', '/recipes/{id}/products', 200);
    }

    public function testListTheProductsOfAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/recipes/999999/products');

        $this->assertSame(['status' => 404, 'error' => 'Recipe not found.'], $response['body']);
        $this->assertDocumented('GET', '/recipes/{id}/products', 404);
    }

    public function testLinkAProductAnswers201(): void
    {
        $response = $this->createLink('R-RP-ADD');

        $this->assertSame(201, $response['status']);
        $this->assertSame(0, $response['body']['is_default']);
        $this->assertDocumented('POST', '/recipes/{id}/products', 201);
    }

    public function testLinkAProductTwiceIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/recipes/1/products', ['product_id' => 1]);

        $this->assertSame(['status' => 400, 'error' => 'This product is already linked to the recipe.'], $response['body']);
        $this->assertDocumented('POST', '/recipes/{id}/products', 400);
    }

    public function testLinkAProductToAnUnknownRecipeIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/recipes/999999/products', ['product_id' => 1]);

        $this->assertSame(['status' => 404, 'error' => 'Recipe not found.'], $response['body']);
        $this->assertDocumented('POST', '/recipes/{id}/products', 404);
    }

    public function testMakeALinkTheDefault(): void
    {
        $id = $this->createLink('R-RP-UPD')['body']['id'];

        $response = $this->request('PUT', "/backend/recipe-products/$id", ['is_default' => true]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(1, $response['body']['is_default']);
        $this->assertDocumented('PUT', '/recipe-products/{id}', 200);
    }

    public function testUpdateALinkWithInvalidDataIsABadRequest(): void
    {
        $id = $this->createLink('R-RP-BAD')['body']['id'];

        $response = $this->request('PUT', "/backend/recipe-products/$id", ['is_default' => 'yes']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/recipe-products/{id}', 400);
    }

    public function testUpdateAnUnknownLinkIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/recipe-products/999999', ['is_default' => true]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/recipe-products/{id}', 404);
    }

    public function testRemoveALink(): void
    {
        $id = $this->createLink('R-RP-DEL')['body']['id'];

        $response = $this->request('DELETE', "/backend/recipe-products/$id");

        $this->assertSame(['message' => 'Product link removed.'], $response['body']);
        $this->assertDocumented('DELETE', '/recipe-products/{id}', 200);
    }

    public function testRemoveAnUnknownLinkIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/recipe-products/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/recipe-products/{id}', 404);
    }
}
