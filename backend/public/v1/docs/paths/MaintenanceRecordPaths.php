<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/MaintenanceRecordRoutes.php. MaintenanceRecordRoutesTest checks every status.
class MaintenanceRecordPaths
{
    #[OA\Get(
        path: '/maintenance-records',
        tags: ['maintenance records'],
        summary: 'List the maintenance log, newest first',
        responses: [
            new OA\Response(response: 200, description: 'All records with machine and user names', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/MaintenanceRecord'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function getMaintenanceRecords() {}

    #[OA\Get(
        path: '/maintenance-records/{id}',
        tags: ['maintenance records'],
        summary: 'Get one maintenance record',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The record', content: new OA\JsonContent(ref: '#/components/schemas/MaintenanceRecord')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Maintenance record not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getMaintenanceRecordsById() {}

    #[OA\Post(
        path: '/maintenance-records',
        tags: ['maintenance records'],
        summary: 'Log finished maintenance work',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaintenanceRecordInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created record', content: new OA\JsonContent(ref: '#/components/schemas/MaintenanceRecord')),
            new OA\Response(response: 400, description: 'Invalid data, unknown type, wrong time order, unknown machine, or an unknown or deactivated user', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function postMaintenanceRecords() {}

    #[OA\Put(
        path: '/maintenance-records/{id}',
        tags: ['maintenance records'],
        summary: 'Correct a maintenance record (every field can change)',
        description: 'Fields left out keep their saved value. A deactivated user already on the record may stay; changing to a deactivated user is refused.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MaintenanceRecordInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated record', content: new OA\JsonContent(ref: '#/components/schemas/MaintenanceRecord')),
            new OA\Response(response: 400, description: 'Invalid data, wrong time order, unknown machine or user', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Maintenance record not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putMaintenanceRecordsById() {}

    #[OA\Delete(
        path: '/maintenance-records/{id}',
        tags: ['maintenance records'],
        summary: 'Delete a maintenance record',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 3, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Maintenance record not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteMaintenanceRecordsById() {}
}
