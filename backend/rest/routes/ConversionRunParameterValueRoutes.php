<?php
// Listed and added through the conversion (its id is in the URL), corrected and deleted through the value's own id.
Flight::route('GET /conversion-runs/@id:[0-9]+/parameter-values', function ($id) {
    Flight::json(Flight::conversionRunParameterValueService()->getByConversion($id));
});

Flight::route('POST /conversion-runs/@id:[0-9]+/parameter-values', function ($id) {
    Flight::json(Flight::conversionRunParameterValueService()->addToConversion($id, Flight::request()->data->getData()), 201);
});

Flight::route('PUT /conversion-run-parameter-values/@id:[0-9]+', function ($id) {
    Flight::json(Flight::conversionRunParameterValueService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /conversion-run-parameter-values/@id:[0-9]+', function ($id) {
    Flight::conversionRunParameterValueService()->delete($id);
    Flight::json(['message' => 'Conversion parameter value deleted.']);
});
