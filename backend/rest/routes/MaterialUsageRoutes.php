<?php
// Every write moves the material's stock too: adding takes, deleting gives back, changing moves the difference.
Flight::route('GET /production-runs/@id:[0-9]+/material-usages', function ($id) {
    Flight::json(Flight::materialUsageService()->getByRun($id));
});

Flight::route('POST /production-runs/@id:[0-9]+/material-usages', function ($id) {
    Flight::json(Flight::materialUsageService()->addToRun($id, Flight::request()->data->getData()), 201);
});

Flight::route('PUT /material-usages/@id:[0-9]+', function ($id) {
    Flight::json(Flight::materialUsageService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /material-usages/@id:[0-9]+', function ($id) {
    Flight::materialUsageService()->delete($id);
    Flight::json(['message' => 'Material usage deleted.']);
});
