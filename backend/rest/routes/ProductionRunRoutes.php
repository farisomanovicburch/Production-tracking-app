<?php
// The list is one row per run; one run comes with its parameter values and material usages.
Flight::route('GET /production-runs', function () {
    Flight::json(Flight::productionRunService()->getAll());
});

Flight::route('GET /production-runs/@id:[0-9]+', function ($id) {
    Flight::json(Flight::productionRunService()->getById($id));
});

// One request for the whole run (values and usages nested), saved in one transaction with the stock decrease.
Flight::route('POST /production-runs', function () {
    Flight::json(Flight::productionRunService()->add(Flight::request()->data->getData()), 201);
});

// The run's own fields only; machine, product, recipe and user are locked, children have their own routes.
Flight::route('PUT /production-runs/@id:[0-9]+', function ($id) {
    Flight::json(Flight::productionRunService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /production-runs/@id:[0-9]+', function ($id) {
    Flight::productionRunService()->delete($id);
    Flight::json(['message' => 'Production run deleted.']);
});
