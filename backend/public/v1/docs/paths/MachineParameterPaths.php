<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MachineParameterRoutes.php. MachineParameterRoutesTest checks every status.
class MachineParameterPaths
{
    #[OA\Get(
        path: '/machines/{id}/parameters',
        tags: ['machine parameters'],
        summary: "List a machine's parameters in display order",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Machine id', example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The parameters, active and deactivated', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/MachineParameter'))),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMachineParameters() {}

    #[OA\Post(
        path: '/machines/{id}/parameters',
        tags: ['machine parameters'],
        summary: 'Add a parameter to a machine',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Machine id', example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MachineParameterInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created parameter', content: new OA\JsonContent(ref: '#/components/schemas/MachineParameter')),
            new OA\Response(response: 400, description: 'Invalid data or the machine already has a parameter with this name', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Machine not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postMachineParameters() {}

    #[OA\Get(
        path: '/machine-parameters/{id}',
        tags: ['machine parameters'],
        summary: 'Get one parameter',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The parameter', content: new OA\JsonContent(ref: '#/components/schemas/MachineParameter')),
            new OA\Response(response: 404, description: 'Machine parameter not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMachineParametersById() {}

    #[OA\Put(
        path: '/machine-parameters/{id}',
        tags: ['machine parameters'],
        summary: 'Update a parameter',
        description: 'A parameter stays on its machine; a machine_id in the body is ignored. Set is_active to false to stop new runs from using it.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MachineParameterInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated parameter', content: new OA\JsonContent(ref: '#/components/schemas/MachineParameter')),
            new OA\Response(response: 400, description: 'Invalid data or the name is taken on this machine', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Machine parameter not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMachineParametersById() {}

    #[OA\Delete(
        path: '/machine-parameters/{id}',
        tags: ['machine parameters'],
        summary: 'Delete a parameter no run has used',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 11, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'Runs or conversions have values for it: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Machine parameter not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMachineParametersById() {}
}
