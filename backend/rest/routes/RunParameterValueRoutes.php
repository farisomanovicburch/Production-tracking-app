<?php
// Listed and added through the run (its id is in the URL), corrected and deleted through the value's own id.
Flight::route('GET /production-runs/@id:[0-9]+/parameter-values', function ($id) {
    Flight::json(Flight::runParameterValueService()->getByRun($id));
});

Flight::route('POST /production-runs/@id:[0-9]+/parameter-values', function ($id) {
    Flight::json(Flight::runParameterValueService()->addToRun($id, Flight::request()->data->getData()), 201);
});

Flight::route('PUT /run-parameter-values/@id:[0-9]+', function ($id) {
    Flight::json(Flight::runParameterValueService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /run-parameter-values/@id:[0-9]+', function ($id) {
    Flight::runParameterValueService()->delete($id);
    Flight::json(['message' => 'Run parameter value deleted.']);
});
