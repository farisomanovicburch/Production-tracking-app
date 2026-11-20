<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/RecipeProductService.php';

final class RecipeProductServiceTest extends DatabaseTestCase
{
    private RecipeProductService $service;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeProductService();
        $this->productId = $this->createProduct();
    }

    private function link(int $recipeId, bool $isDefault = false, ?RecipeProductService $service = null): array
    {
        return ($service ?? $this->service)->addToRecipe($recipeId, ['product_id' => $this->productId, 'is_default' => $isDefault]);
    }

    private function isDefault(int $linkId): int
    {
        $statement = $this->db->prepare('SELECT is_default FROM recipe_products WHERE id = :id');
        $statement->execute(['id' => $linkId]);
        return (int) $statement->fetchColumn();
    }

    private function assertNotFound(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Accepted, expected: $message");
        } catch (NotFoundException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /* ---------- list ---------- */

    public function testGetByRecipeListsTheLinkedProductsWithTheirNames(): void
    {
        $recipeId = $this->createRecipe();
        $this->service->addToRecipe($recipeId, ['product_id' => $this->createProduct('Test film')]);

        $this->assertSame(['Test film'], array_column($this->service->getByRecipe($recipeId), 'product_name'));
    }

    public function testGetByRecipeOfAnUnknownRecipeIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByRecipe(999999), 'Recipe not found.');
    }

    /* ---------- add ---------- */

    public function testANewLinkIsNotTheDefaultUnlessToldSo(): void
    {
        $recipeId = $this->createRecipe();

        $link = $this->service->addToRecipe($recipeId, ['product_id' => $this->productId]);

        $this->assertSame([$recipeId, $this->productId, 0], [$link['recipe_id'], $link['product_id'], $link['is_default']]);
    }

    public function testAddToAnUnknownRecipeIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->link(999999), 'Recipe not found.');
    }

    public function testAddRequiresAProduct(): void
    {
        $this->assertRejected(fn () => $this->service->addToRecipe($this->createRecipe(), []), "Field 'product_id' is required.");
    }

    public function testAddRejectsAProductThatDoesNotExist(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToRecipe($this->createRecipe(), ['product_id' => 999999]),
            'The selected product does not exist.'
        );
    }

    public function testAddRejectsAProductThatIsAlreadyLinked(): void
    {
        $recipeId = $this->createRecipe();
        $this->link($recipeId);

        $this->assertRejected(fn () => $this->link($recipeId), 'This product is already linked to the recipe.');
    }

    public function testAddRejectsAnIsDefaultThatIsNotAFlag(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToRecipe($this->createRecipe(), ['product_id' => $this->productId, 'is_default' => 'yes']),
            "Field 'is_default' must be true or false."
        );
    }

    public function testAddWithoutTheRecipeFromTheUrlIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToRecipe(): the recipe comes from the URL.');

        $this->service->add(['recipe_id' => $this->createRecipe(), 'product_id' => $this->productId]);
    }

    /* ---------- one default recipe per product ---------- */

    public function testTheFirstDefaultOfAProductIsSaved(): void
    {
        $this->assertSame(1, $this->link($this->createRecipe(), true)['is_default']);
    }

    // The run form preselects the default recipe; with two defaults it could not know which one to pick.
    public function testANewDefaultReplacesTheOldOne(): void
    {
        $oldLinkId = $this->link($this->createRecipe(), true)['id'];

        $newLink = $this->link($this->createRecipe(), true);

        $this->assertSame([0, 1], [$this->isDefault($oldLinkId), $newLink['is_default']]);
    }

    public function testADefaultOfAnotherProductStaysUntouched(): void
    {
        $recipeId = $this->createRecipe();
        $otherLinkId = $this->service->addToRecipe($recipeId, ['product_id' => $this->createProduct(), 'is_default' => true])['id'];

        $this->link($this->createRecipe(), true);

        $this->assertSame(1, $this->isDefault($otherLinkId));
    }

    // Unsetting the old default and saving the new link happen in one transaction: if saving fails, the product
    // keeps its old default instead of being left with none.
    public function testWhenSavingTheNewDefaultFailsTheOldOneStays(): void
    {
        $oldLinkId = $this->link($this->createRecipe(), true)['id'];
        $failingDao = new class extends RecipeProductDao {
            public function add($entity)
            {
                throw new RuntimeException('The database went away.');
            }
        };

        try {
            $this->link($this->createRecipe(), true, new RecipeProductService($failingDao));
            $this->fail('The failing save was not reported.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertSame(1, $this->isDefault($oldLinkId));
    }

    /* ---------- update: only is_default can change ---------- */

    public function testUpdateCanMakeALinkTheDefaultAndUnsetsTheOldOne(): void
    {
        $oldLinkId = $this->link($this->createRecipe(), true)['id'];
        $linkId = $this->link($this->createRecipe())['id'];

        $updated = $this->service->update(['is_default' => true], $linkId);

        $this->assertSame([0, 1], [$this->isDefault($oldLinkId), $updated['is_default']]);
    }

    // Saving the default link as default again must not unset it.
    public function testUpdateKeepsTheDefaultWhenItIsAlreadyTheDefault(): void
    {
        $linkId = $this->link($this->createRecipe(), true)['id'];

        $this->assertSame(1, $this->service->update(['is_default' => true], $linkId)['is_default']);
    }

    public function testUpdateCanUnsetTheDefault(): void
    {
        $linkId = $this->link($this->createRecipe(), true)['id'];

        $this->assertSame(0, $this->service->update(['is_default' => false], $linkId)['is_default']);
    }

    public function testWhenSavingTheUpdateFailsTheOldDefaultStays(): void
    {
        $oldLinkId = $this->link($this->createRecipe(), true)['id'];
        $linkId = $this->link($this->createRecipe())['id'];
        $failingDao = new class extends RecipeProductDao {
            public $failForId;

            public function update($entity, $id)
            {
                if ($id == $this->failForId) {
                    throw new RuntimeException('The database went away.');
                }
                return parent::update($entity, $id);
            }
        };
        $failingDao->failForId = $linkId;

        try {
            (new RecipeProductService($failingDao))->update(['is_default' => true], $linkId);
            $this->fail('The failing save was not reported.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertSame([1, 0], [$this->isDefault($oldLinkId), $this->isDefault($linkId)]);
    }

    // Changing the product or the recipe of a link is removing it and adding another one.
    public function testUpdateIgnoresAProductOrRecipeInTheBody(): void
    {
        $recipeId = $this->createRecipe();
        $linkId = $this->link($recipeId)['id'];

        $updated = $this->service->update(['is_default' => false, 'product_id' => $this->createProduct(), 'recipe_id' => $this->createRecipe()], $linkId);

        $this->assertSame([$recipeId, $this->productId], [$updated['recipe_id'], $updated['product_id']]);
    }

    public function testUpdateRequiresIsDefault(): void
    {
        $linkId = $this->link($this->createRecipe())['id'];

        $this->assertRejected(fn () => $this->service->update([], $linkId), "Field 'is_default' is required.");
    }

    public function testUpdateOfAnUnknownLinkIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['is_default' => true], 999999), 'Recipe product link not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheLinkButNotTheProduct(): void
    {
        $linkId = $this->link($this->createRecipe())['id'];

        $this->service->delete($linkId);

        $this->assertSame([0, 1], [$this->countRows('recipe_products', 'id', $linkId), $this->countRows('products', 'id', $this->productId)]);
    }
}
