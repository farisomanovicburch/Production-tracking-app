<?php
// The list is one row per conversion; one conversion comes with its parameter values. Scrap is calculated.
Flight::route('GET /conversion-runs', function () {
    Flight::json(Flight::conversionRunService()->getAll());
});

Flight::route('GET /conversion-runs/@id:[0-9]+', function ($id) {
    Flight::json(Flight::conversionRunService()->getById($id));
});

// One request for the whole job (values nested), saved in one transaction.
Flight::route('POST /conversion-runs', function () {
    Flight::json(Flight::conversionRunService()->add(Flight::request()->data->getData()), 201);
});

// The conversion's own fields only; machine, products and user are locked, values have their own routes.
Flight::route('PUT /conversion-runs/@id:[0-9]+', function ($id) {
    Flight::json(Flight::conversionRunService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /conversion-runs/@id:[0-9]+', function ($id) {
    Flight::conversionRunService()->delete($id);
    Flight::json(['message' => 'Conversion run deleted.']);
});
