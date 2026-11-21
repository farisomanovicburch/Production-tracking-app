<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/ConversionRunRoutes.php. ConversionRunRoutesTest checks every status.
class ConversionRunPaths
{
    #[OA\Get(
        path: '/conversion-runs',
        tags: ['conversion runs'],
        summary: 'List all conversions, newest first (without parameter values)',
        responses: [
            new OA\Response(response: 200, description: 'All conversions with names and calculated scrap', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ConversionRun')))
        ]
    )]
    public function getConversionRuns() {}

    #[OA\Get(
        path: '/conversion-runs/{id}',
        tags: ['conversion runs'],
        summary: 'Get one conversion with its parameter values',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The conversion', content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunWithDetails')),
            new OA\Response(response: 404, description: 'Conversion run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getConversionRunsById() {}

    #[OA\Post(
        path: '/conversion-runs',
        tags: ['conversion runs'],
        summary: 'Log a finished bag-making job with its parameter values',
        description: 'Saved in one transaction: if a parameter value breaks a rule, the conversion is not saved either. No stock moves.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created conversion', content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunWithDetails')),
            new OA\Response(
                response: 400,
                description: 'Invalid data, output above input, wrong time order, a machine/product/user that does not fit or is deactivated, or missing parameter values',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            )
        ]
    )]
    public function postConversionRuns() {}

    #[OA\Put(
        path: '/conversion-runs/{id}',
        tags: ['conversion runs'],
        summary: "Correct a conversion's times, amounts or notes",
        description: 'machine_id, user_id, input_product_id and output_product_id are ignored (locked after create). Values have their own routes.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'The updated conversion', content: new OA\JsonContent(ref: '#/components/schemas/ConversionRunWithDetails')),
            new OA\Response(response: 400, description: 'Invalid data, output above input or wrong time order', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Conversion run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putConversionRunsById() {}

    #[OA\Delete(
        path: '/conversion-runs/{id}',
        tags: ['conversion runs'],
        summary: 'Delete a conversion and its parameter values',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 3, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Conversion run not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteConversionRunsById() {}
}
