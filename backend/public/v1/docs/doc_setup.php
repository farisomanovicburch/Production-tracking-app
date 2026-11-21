<?php
use OpenApi\Attributes as OA;

/*
 * General information about the API and the schemas shared by many endpoints (referenced from paths/ with
 * ref: '#/components/schemas/...'). The attributes need a class to sit on, so the class itself is empty.
 * Plain block comments only: swagger-php would turn a doc comment into a description.
 */
#[OA\Info(
    title: 'Production Tracker API',
    description: 'REST API of Production Tracker: production runs, conversions, maintenance and material stock ' .
        'of a plastic film and bag factory. IT 2001 Web Programming course project.',
    version: '1.0'
)]
#[OA\Server(url: API_BASE_URL, description: 'This server')]

/* ---------- shared answers: must match what the error handler and the DELETE routes send ---------- */
#[OA\Schema(
    schema: 'Error',
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 400),
        new OA\Property(property: 'error', type: 'string', example: "Field 'email' is required.")
    ]
)]
#[OA\Schema(
    schema: 'Message',
    properties: [new OA\Property(property: 'message', type: 'string', example: 'User deleted.')]
)]

/* ---------- users: input is not output (the password goes in, never out) ---------- */
#[OA\Schema(
    schema: 'UserInput',
    required: ['full_name', 'email', 'password'],
    properties: [
        new OA\Property(property: 'full_name', type: 'string', example: 'Test Operator'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test.operator@example.com'),
        new OA\Property(
            property: 'password',
            type: 'string',
            example: 'secret123',
            description: 'At least 8 characters with a letter and a number. Required when creating; leave it out when updating to keep the current one.'
        ),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'operator'], example: 'operator', description: 'Default: operator'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true. A deactivated user cannot log in.')
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 4),
        new OA\Property(property: 'full_name', type: 'string', example: 'Test Operator'),
        new OA\Property(property: 'email', type: 'string', example: 'test.operator@example.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'operator'], example: 'operator'),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1),
        new OA\Property(property: 'created_at', type: 'string', example: '2026-11-19 08:00:00')
    ]
)]
/*
 * ---------- machines ----------
 * Machine and MachineParameter are written out instead of built with allOf from their input: allOf means "valid
 * against every part", and is_active goes in as a boolean but comes out as 0/1, which no value can be at once.
 */
#[OA\Schema(
    schema: 'MachineInput',
    required: ['name', 'code', 'machine_type'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Extruder 4'),
        new OA\Property(property: 'code', type: 'string', example: 'EX-04', description: 'Unique, 2 to 20 characters'),
        new OA\Property(
            property: 'machine_type',
            type: 'string',
            enum: ['extrusion', 'conversion'],
            example: 'extrusion',
            description: 'Required when creating; cannot be changed afterwards (leave it out or send the same value).'
        ),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true')
    ]
)]
#[OA\Schema(
    schema: 'Machine',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'name', type: 'string', example: 'Extruder 4'),
        new OA\Property(property: 'code', type: 'string', example: 'EX-04'),
        new OA\Property(property: 'machine_type', type: 'string', enum: ['extrusion', 'conversion'], example: 'extrusion'),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1),
        new OA\Property(property: 'created_at', type: 'string', example: '2026-11-20 08:00:00')
    ]
)]
#[OA\Schema(
    schema: 'MachineParameterInput',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Zone 2 temperature', description: 'Unique per machine'),
        new OA\Property(property: 'unit', type: 'string', nullable: true, example: '°C', description: 'Leave empty for a plain number'),
        new OA\Property(property: 'display_order', type: 'integer', minimum: 0, example: 4, description: 'Default when creating: after the last parameter'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true. New runs only take values for active parameters.')
    ]
)]
#[OA\Schema(
    schema: 'MachineParameter',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 11),
        new OA\Property(property: 'machine_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Zone 2 temperature'),
        new OA\Property(property: 'unit', type: 'string', nullable: true, example: '°C'),
        new OA\Property(property: 'display_order', type: 'integer', example: 4),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1)
    ]
)]
/* A link has no boolean, so its output can safely be its input plus the ids (allOf). */
#[OA\Schema(
    schema: 'MachineProductInput',
    required: ['product_id', 'role'],
    properties: [
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'role', type: 'string', enum: ['input', 'output'], example: 'output', description: 'An extrusion machine only has output products.')
    ]
)]
#[OA\Schema(
    schema: 'MachineProduct',
    allOf: [
        new OA\Schema(properties: [
            new OA\Property(property: 'id', type: 'integer', example: 10),
            new OA\Property(property: 'machine_id', type: 'integer', example: 1)
        ]),
        new OA\Schema(ref: '#/components/schemas/MachineProductInput')
    ]
)]
#[OA\Schema(
    schema: 'MachineProductWithName',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/MachineProduct'),
        new OA\Schema(properties: [
            new OA\Property(property: 'product_code', type: 'string', example: 'P-100'),
            new OA\Property(property: 'product_name', type: 'string', example: 'LDPE film 1000 mm')
        ])
    ]
)]
/* ---------- products and materials (DECIMAL columns come back as strings, e.g. "500.000") ---------- */
#[OA\Schema(
    schema: 'ProductInput',
    required: ['code', 'name'],
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'P-120', description: 'Unique, 2 to 20 characters'),
        new OA\Property(property: 'name', type: 'string', example: 'LDPE film, blue'),
        new OA\Property(property: 'width_mm', type: 'integer', nullable: true, minimum: 1, example: 1000),
        new OA\Property(property: 'thickness_um', type: 'integer', nullable: true, minimum: 1, example: 50),
        new OA\Property(property: 'length_m', type: 'number', nullable: true, minimum: 0.01, example: 0.5, description: 'For bags: length of one piece'),
        new OA\Property(property: 'unit', type: 'string', enum: ['kg', 'pcs'], example: 'kg', description: 'Default: kg. Film is counted in kg, bags in pieces.'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Blue masterbatch 2 %'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true')
    ]
)]
#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'code', type: 'string', example: 'P-120'),
        new OA\Property(property: 'name', type: 'string', example: 'LDPE film, blue'),
        new OA\Property(property: 'width_mm', type: 'integer', nullable: true, example: 1000),
        new OA\Property(property: 'thickness_um', type: 'integer', nullable: true, example: 50),
        new OA\Property(property: 'length_m', type: 'string', nullable: true, example: '0.50'),
        new OA\Property(property: 'unit', type: 'string', enum: ['kg', 'pcs'], example: 'kg'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Blue masterbatch 2 %'),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1)
    ]
)]
#[OA\Schema(
    schema: 'MaterialInput',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'LLDPE granulate', description: 'Unique'),
        new OA\Property(property: 'unit', type: 'string', example: 'kg', description: 'Default: kg'),
        new OA\Property(property: 'supplier', type: 'string', nullable: true, example: 'Supplier D'),
        new OA\Property(
            property: 'stock_qty',
            type: 'number',
            minimum: 0,
            example: 250,
            description: 'Only when creating (default 0). Ignored on update: use PUT /materials/{id}/delivery.'
        ),
        new OA\Property(property: 'min_stock_qty', type: 'number', nullable: true, minimum: 0, example: 100, description: 'Leave empty for no low-stock warning'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true')
    ]
)]
#[OA\Schema(
    schema: 'Material',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 6),
        new OA\Property(property: 'name', type: 'string', example: 'LLDPE granulate'),
        new OA\Property(property: 'unit', type: 'string', example: 'kg'),
        new OA\Property(property: 'supplier', type: 'string', nullable: true, example: 'Supplier D'),
        new OA\Property(property: 'stock_qty', type: 'string', example: '250.000'),
        new OA\Property(property: 'min_stock_qty', type: 'string', nullable: true, example: '100.000'),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1)
    ]
)]
#[OA\Schema(
    schema: 'Delivery',
    required: ['quantity_kg'],
    properties: [new OA\Property(property: 'quantity_kg', type: 'number', minimum: 0.001, example: 25.5, description: 'Added to the stock; more than 0')]
)]
/* ---------- recipes ---------- */
#[OA\Schema(
    schema: 'RecipeInput',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Blue film', description: 'Unique'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Mix the masterbatch first.'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Default: true')
    ]
)]
#[OA\Schema(
    schema: 'Recipe',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 4),
        new OA\Property(property: 'name', type: 'string', example: 'Blue film'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Mix the masterbatch first.'),
        new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 1)
    ]
)]
#[OA\Schema(
    schema: 'RecipeItemInput',
    required: ['material_id', 'percentage'],
    properties: [
        new OA\Property(property: 'material_id', type: 'integer', example: 2, description: 'Not already in the recipe'),
        new OA\Property(property: 'percentage', type: 'number', minimum: 0.01, maximum: 100, example: 95, description: 'All items of a recipe may add up to at most 100')
    ]
)]
#[OA\Schema(
    schema: 'RecipeItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 9),
        new OA\Property(property: 'recipe_id', type: 'integer', example: 4),
        new OA\Property(property: 'material_id', type: 'integer', example: 2),
        new OA\Property(property: 'percentage', type: 'string', example: '95.00')
    ]
)]
#[OA\Schema(
    schema: 'RecipeItemWithName',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/RecipeItem'),
        new OA\Schema(properties: [new OA\Property(property: 'material_name', type: 'string', example: 'HDPE granulate')])
    ]
)]
#[OA\Schema(
    schema: 'RecipeProductInput',
    required: ['product_id'],
    properties: [
        new OA\Property(property: 'product_id', type: 'integer', example: 1, description: 'Only when linking; cannot be changed afterwards'),
        new OA\Property(
            property: 'is_default',
            type: 'boolean',
            example: false,
            description: "Default: false. true makes this the product's default recipe and unsets its previous default."
        )
    ]
)]
#[OA\Schema(
    schema: 'RecipeProductUpdate',
    required: ['is_default'],
    properties: [new OA\Property(property: 'is_default', type: 'boolean', example: true, description: "true unsets the product's previous default recipe")]
)]
#[OA\Schema(
    schema: 'RecipeProduct',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'recipe_id', type: 'integer', example: 4),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'is_default', type: 'integer', enum: [0, 1], example: 0)
    ]
)]
#[OA\Schema(
    schema: 'RecipeProductWithName',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/RecipeProduct'),
        new OA\Schema(properties: [
            new OA\Property(property: 'product_code', type: 'string', example: 'P-100'),
            new OA\Property(property: 'product_name', type: 'string', example: 'LDPE film, clear')
        ])
    ]
)]
/* ---------- production runs: times as datetime-local ("2026-11-02T06:00") or "2026-11-02 06:00:00" ---------- */
#[OA\Schema(
    schema: 'RunParameterValueInput',
    required: ['machine_parameter_id', 'value'],
    properties: [
        new OA\Property(property: 'machine_parameter_id', type: 'integer', example: 1, description: "An active parameter of the run's machine, not already recorded for the run"),
        new OA\Property(property: 'value', type: 'number', example: 180)
    ]
)]
#[OA\Schema(
    schema: 'RunParameterValueUpdate',
    required: ['value'],
    properties: [new OA\Property(property: 'value', type: 'number', example: 183.5, description: 'Only the number can change, not the parameter')]
)]
#[OA\Schema(
    schema: 'RunParameterValue',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 13),
        new OA\Property(property: 'production_run_id', type: 'integer', example: 5),
        new OA\Property(property: 'machine_parameter_id', type: 'integer', example: 1),
        new OA\Property(property: 'value', type: 'string', example: '180.000')
    ]
)]
#[OA\Schema(
    schema: 'RunParameterValueWithName',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/RunParameterValue'),
        new OA\Schema(properties: [
            new OA\Property(property: 'parameter_name', type: 'string', example: 'Zone 1 temperature'),
            new OA\Property(property: 'parameter_unit', type: 'string', nullable: true, example: '°C')
        ])
    ]
)]
#[OA\Schema(
    schema: 'MaterialUsageInput',
    required: ['material_id', 'quantity_kg'],
    properties: [
        new OA\Property(property: 'material_id', type: 'integer', example: 1, description: "A material of the run's recipe, not already used in the run"),
        new OA\Property(property: 'quantity_kg', type: 'number', minimum: 0.001, example: 10, description: 'Taken from the stock; refused if the stock is too low')
    ]
)]
#[OA\Schema(
    schema: 'MaterialUsageUpdate',
    required: ['quantity_kg'],
    properties: [new OA\Property(property: 'quantity_kg', type: 'number', minimum: 0.001, example: 25, description: 'The stock moves by the difference to the saved amount')]
)]
#[OA\Schema(
    schema: 'MaterialUsage',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 11),
        new OA\Property(property: 'production_run_id', type: 'integer', example: 5),
        new OA\Property(property: 'material_id', type: 'integer', example: 1),
        new OA\Property(property: 'quantity_kg', type: 'string', example: '10.000')
    ]
)]
#[OA\Schema(
    schema: 'MaterialUsageWithName',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/MaterialUsage'),
        new OA\Schema(properties: [new OA\Property(property: 'material_name', type: 'string', example: 'LDPE granulate')])
    ]
)]
#[OA\Schema(
    schema: 'ProductionRunUpdate',
    description: 'Every field is optional: a field left out keeps its saved value, an empty optional field is cleared.',
    properties: [
        new OA\Property(property: 'warmup_started_at', type: 'string', nullable: true, example: '2026-11-02T05:30', description: 'Before started_at'),
        new OA\Property(property: 'started_at', type: 'string', example: '2026-11-02T06:00'),
        new OA\Property(property: 'stable_at', type: 'string', nullable: true, example: '2026-11-02T06:45', description: 'After started_at and before ended_at'),
        new OA\Property(property: 'ended_at', type: 'string', example: '2026-11-02T14:00', description: 'After started_at'),
        new OA\Property(property: 'quantity_produced', type: 'number', minimum: 0, example: 870, description: "In the product's unit (kg or pcs)"),
        new OA\Property(property: 'net_weight_per_unit_kg', type: 'number', nullable: true, example: 25),
        new OA\Property(property: 'gross_weight_per_unit_kg', type: 'number', nullable: true, example: 25.8, description: 'Not less than the net weight'),
        new OA\Property(property: 'scrap_kg', type: 'number', minimum: 0, example: 42, description: 'Default: 0'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Rolls of 25 kg on 0.8 kg cores.')
    ]
)]
#[OA\Schema(
    schema: 'ProductionRunInput',
    required: ['machine_id', 'product_id', 'recipe_id', 'user_id', 'started_at', 'ended_at', 'quantity_produced', 'material_usages'],
    allOf: [
        new OA\Schema(properties: [
            new OA\Property(property: 'machine_id', type: 'integer', example: 1, description: 'An active extrusion machine'),
            new OA\Property(property: 'product_id', type: 'integer', example: 1, description: 'An active product the machine has as output'),
            new OA\Property(property: 'recipe_id', type: 'integer', example: 1, description: 'An active recipe linked to the product, adding up to 100 %'),
            new OA\Property(property: 'user_id', type: 'integer', example: 2, description: 'An active user (from the login token in Milestone 4)'),
            new OA\Property(
                property: 'parameter_values',
                type: 'array',
                description: "One value for every active parameter of the machine",
                items: new OA\Items(ref: '#/components/schemas/RunParameterValueInput')
            ),
            new OA\Property(
                property: 'material_usages',
                type: 'array',
                description: 'At least one; only materials of the recipe',
                items: new OA\Items(ref: '#/components/schemas/MaterialUsageInput')
            )
        ]),
        new OA\Schema(ref: '#/components/schemas/ProductionRunUpdate')
    ]
)]
#[OA\Schema(
    schema: 'ProductionRun',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'machine_id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'recipe_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 2),
        new OA\Property(property: 'warmup_started_at', type: 'string', nullable: true, example: '2026-11-02 05:30:00'),
        new OA\Property(property: 'started_at', type: 'string', example: '2026-11-02 06:00:00'),
        new OA\Property(property: 'stable_at', type: 'string', nullable: true, example: '2026-11-02 06:45:00'),
        new OA\Property(property: 'ended_at', type: 'string', example: '2026-11-02 14:00:00'),
        new OA\Property(property: 'quantity_produced', type: 'string', example: '870.000'),
        new OA\Property(property: 'net_weight_per_unit_kg', type: 'string', nullable: true, example: '25.0000'),
        new OA\Property(property: 'gross_weight_per_unit_kg', type: 'string', nullable: true, example: '25.8000'),
        new OA\Property(property: 'scrap_kg', type: 'string', example: '42.000'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Rolls of 25 kg on 0.8 kg cores.'),
        new OA\Property(property: 'created_at', type: 'string', example: '2026-11-02 14:10:00'),
        new OA\Property(property: 'updated_at', type: 'string', example: '2026-11-02 14:10:00'),
        new OA\Property(property: 'machine_name', type: 'string', example: 'Extruder 1'),
        new OA\Property(property: 'product_name', type: 'string', example: 'LDPE film, clear'),
        new OA\Property(property: 'product_unit', type: 'string', enum: ['kg', 'pcs'], example: 'kg'),
        new OA\Property(property: 'recipe_name', type: 'string', example: 'Clear film'),
        new OA\Property(property: 'user_name', type: 'string', example: 'Demo Operator')
    ]
)]
#[OA\Schema(
    schema: 'ProductionRunWithDetails',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ProductionRun'),
        new OA\Schema(properties: [
            new OA\Property(property: 'parameter_values', type: 'array', items: new OA\Items(ref: '#/components/schemas/RunParameterValueWithName')),
            new OA\Property(property: 'material_usages', type: 'array', items: new OA\Items(ref: '#/components/schemas/MaterialUsageWithName'))
        ])
    ]
)]
class OpenApiSpec {}
