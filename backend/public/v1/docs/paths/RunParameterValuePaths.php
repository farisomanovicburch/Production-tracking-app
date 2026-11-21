<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/RunParameterValueRoutes.php. RunParameterValueRoutesTest checks every status.
class RunParameterValuePaths
{
    #[OA\Get(
        path: '/production-runs/{id}/parameter-values',
        tags: ['run parameter values'],
        summary: "List a run's parameter values in the machine's display order",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Production run id', example: 2, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The values with parameter names and units', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RunParameterValueWithName'))),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getRunParameterValues() {}

    #[OA\Post(
        path: '/production-runs/{id}/parameter-values',
        tags: ['run parameter values'],
        summary: 'Add a value to an existing run',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Production run id', example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValueInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created value', content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValue')),
            new OA\Response(response: 400, description: "Invalid data, or a parameter that is unknown, of another machine, deactivated, or already recorded", content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Production run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postRunParameterValues() {}

    #[OA\Put(
        path: '/run-parameter-values/{id}',
        tags: ['run parameter values'],
        summary: 'Correct a value',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValueUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated value', content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValue')),
            new OA\Response(response: 400, description: 'Missing or not a number', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Run parameter value not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putRunParameterValuesById() {}

    #[OA\Delete(
        path: '/run-parameter-values/{id}',
        tags: ['run parameter values'],
        summary: 'Delete a value',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 13, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Run parameter value not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteRunParameterValuesById() {}
}
