<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MaterialDao.php';

final class MaterialDaoTest extends DatabaseTestCase
{
    private MaterialDao $dao;
    private int $materialId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new MaterialDao();
        $this->materialId = $this->insertRow('materials', ['name' => 'Test material', 'stock_qty' => '10.000']);
    }

    public function testGetByNameFindsTheMaterialInAnyCase(): void
    {
        $this->assertSame($this->materialId, $this->dao->getByName('TEST MATERIAL')['id']);
    }

    public function testGetByNameReturnsNullForAnUnknownName(): void
    {
        $this->assertNull($this->dao->getByName('No such material'));
    }

    public function testChangeStockAddsAPositiveAmount(): void
    {
        $this->dao->changeStock($this->materialId, '2.5');

        $this->assertSame('12.500', $this->dao->getById($this->materialId)['stock_qty']);
    }

    public function testChangeStockSubtractsANegativeAmount(): void
    {
        $this->dao->changeStock($this->materialId, '-10');

        $this->assertSame('0.000', $this->dao->getById($this->materialId)['stock_qty']);
    }

    public function testChangeStockRefusesToGoBelowZeroAndLeavesTheStockAsItWas(): void
    {
        try {
            $this->dao->changeStock($this->materialId, '-10.001');
            $this->fail('Stock below zero was accepted.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('chk_materials_stock_not_negative', $exception->getMessage());
        }

        $this->assertSame('10.000', $this->dao->getById($this->materialId)['stock_qty']);
    }
}
