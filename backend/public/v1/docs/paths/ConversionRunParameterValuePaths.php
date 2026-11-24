<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/ConversionRunParameterValueRoutes.php. ConversionRunParameterValueRoutesTest checks every status.
class ConversionRunParameterValuePaths
{
    #[OA\Get(
        path: '/conversion-runs/{id}/parameter-values',
        tags: ['conversion parameter values'],
        summary: "List a conversion's parameter values in the machine's display order",
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Conversion run id', example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The values with parameter names and units', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ConversionRunParameterValueWithName'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Conversion run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getConversionRunParameterValues() {}

    #[OA\Post(
        path: '/conversion-runs/{id}/parameter-values',
        tags: ['conversion parameter values'],
        summary: 'Add a value to an existing conversion',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Conversion run id', example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValueInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created value', content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunParameterValue')),
            new OA\Response(response: 400, description: 'Invalid data, or a parameter that is unknown, of another machine, deactivated, or already recorded', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Conversion run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postConversionRunParameterValues() {}

    #[OA\Put(
        path: '/conversion-run-parameter-values/{id}',
        tags: ['conversion parameter values'],
        summary: 'Correct a value',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RunParameterValueUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated value', content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunParameterValue')),
            new OA\Response(response: 400, description: 'Missing or not a number', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Conversion parameter value not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putConversionRunParameterValuesById() {}

    #[OA\Delete(
        path: '/conversion-run-parameter-values/{id}',
        tags: ['conversion parameter values'],
        summary: 'Delete a value',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 7, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'Conversion parameter value not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteConversionRunParameterValuesById() {}
}
