<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MachineRoutes.php. MachineRoutesTest checks every status against the real answers.
class MachinePaths
{
    #[OA\Get(
        path: '/machines',
        tags: ['machines'],
        summary: 'List all machines',
        responses: [
            new OA\Response(response: 200, description: 'All machines', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Machine')))
        ]
    )]
    public function getMachines() {}

    #[OA\Get(
        path: '/machines/{id}',
        tags: ['machines'],
        summary: 'Get one machine',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The machine', content: new OA\JsonContent(ref: '#/components/schemas/Machine')),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMachinesById() {}

    #[OA\Post(
        path: '/machines',
        tags: ['machines'],
        summary: 'Create a machine',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MachineInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created machine', content: new OA\JsonContent(ref: '#/components/schemas/Machine')),
            new OA\Response(response: 400, description: 'Invalid data or code already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postMachines() {}

    #[OA\Put(
        path: '/machines/{id}',
        tags: ['machines'],
        summary: 'Update a machine',
        description: 'The machine type cannot be changed. Set is_active to false to take a machine out of use.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MachineInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated machine', content: new OA\JsonContent(ref: '#/components/schemas/Machine')),
            new OA\Response(response: 400, description: 'Invalid data, code already in use or a changed type', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMachinesById() {}

    #[OA\Delete(
        path: '/machines/{id}',
        tags: ['machines'],
        summary: 'Delete a machine with no history',
        description: 'Its parameters and product links are deleted with it.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 5, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'The machine has runs, conversions or maintenance records: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMachinesById() {}
}
