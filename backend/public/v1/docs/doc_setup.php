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
class OpenApiSpec {}
