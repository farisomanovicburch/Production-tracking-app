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
class OpenApiSpec {}
