<?php
require_once __DIR__ . '/../DatabaseTestCase.php';

final class SchemaConstraintsTest extends DatabaseTestCase
{
    public function testMaterialStockCannotGoBelowZero(): void
    {
        $materialId = $this->insertRow('materials', ['name' => 'Test LDPE', 'stock_qty' => 5]);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/chk_materials_stock_not_negative/');
        $this->db->prepare('UPDATE materials SET stock_qty = :stock WHERE id = :id')
            ->execute(['stock' => -1, 'id' => $materialId]);
    }

    public function testTwoUsersCannotShareAnEmail(): void
    {
        $this->insertUser('same@example.com');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/uq_users_email/');
        $this->insertUser('same@example.com');
    }

    public function testDeletingARunAlsoDeletesItsParameterValuesAndMaterialUsages(): void
    {
        $machineId = $this->insertMachine('extrusion');
        $runId = $this->insertRun($machineId);
        $parameterId = $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Temperature']);
        $this->insertRow('run_parameter_values', [
            'production_run_id' => $runId, 'machine_parameter_id' => $parameterId, 'value' => 180,
        ]);
        $materialId = $this->insertRow('materials', ['name' => 'Test LDPE', 'stock_qty' => 100]);
        $this->insertRow('material_usages', [
            'production_run_id' => $runId, 'material_id' => $materialId, 'quantity_kg' => 25,
        ]);

        $this->db->prepare('DELETE FROM production_runs WHERE id = :id')->execute(['id' => $runId]);

        $this->assertSame(0, $this->countRows('run_parameter_values', 'production_run_id', $runId));
        $this->assertSame(0, $this->countRows('material_usages', 'production_run_id', $runId));
    }

    public function testAMachineWithRunsCannotBeDeleted(): void
    {
        $machineId = $this->insertMachine('extrusion');
        $this->insertRun($machineId);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/fk_production_runs_machine/');
        $this->db->prepare('DELETE FROM machines WHERE id = :id')->execute(['id' => $machineId]);
    }

    public function testAConversionCannotOutputMoreThanItsInput(): void
    {
        $row = [
            'machine_id' => $this->insertMachine('conversion'),
            'user_id' => $this->insertUser('schema-test@example.com'),
            'input_product_id' => $this->insertRow('products', ['code' => 'T-FILM', 'name' => 'Test film']),
            'output_product_id' => $this->insertRow('products', ['code' => 'T-BAG', 'name' => 'Test bag', 'unit' => 'pcs']),
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 14:00:00',
            'input_kg' => 100,
            'output_kg' => 120,
            'bag_count' => 1000,
        ];

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/chk_conversion_runs_output_not_above_input/');
        $this->insertRow('conversion_runs', $row);
    }

    public function testARunCannotEndBeforeItStarts(): void
    {
        $machineId = $this->insertMachine('extrusion');

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/chk_production_runs_ends_after_start/');
        $this->insertRun($machineId, ['started_at' => '2026-10-20 14:00:00', 'ended_at' => '2026-10-20 08:00:00']);
    }

    private function insertUser(string $email): int
    {
        return $this->insertRow('users', [
            'full_name' => 'Test Operator', 'email' => $email, 'password_hash' => 'not-a-real-hash',
        ]);
    }

    private function insertMachine(string $type): int
    {
        return $this->insertRow('machines', ['name' => 'Test machine', 'code' => 'T-01', 'machine_type' => $type]);
    }

    private function insertRun(int $machineId, array $overrides = []): int
    {
        return $this->insertRow('production_runs', array_merge([
            'machine_id' => $machineId,
            'product_id' => $this->insertRow('products', ['code' => 'T-FILM', 'name' => 'Test film']),
            'recipe_id' => $this->insertRow('recipes', ['name' => 'Test recipe']),
            'user_id' => $this->insertUser('schema-test@example.com'),
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 14:00:00',
            'quantity_produced' => 500,
        ], $overrides));
    }
}
