<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/ProductDao.php';

final class ProductDaoTest extends DatabaseTestCase
{
    // Same collation as the UNIQUE index: t-film and T-FILM are one code.
    public function testGetByCodeFindsTheProductInAnyCase(): void
    {
        $productId = $this->insertRow('products', ['code' => 'T-FILM', 'name' => 'Test film']);

        $this->assertSame($productId, (new ProductDao())->getByCode('t-film')['id']);
    }

    public function testGetByCodeReturnsNullForAnUnknownCode(): void
    {
        $this->assertNull((new ProductDao())->getByCode('NO-SUCH-CODE'));
    }
}
