<?php
Flight::route('GET /maintenance-records', function () {
    Flight::json(Flight::maintenanceRecordService()->getAll());
});

Flight::route('GET /maintenance-records/@id:[0-9]+', function ($id) {
    Flight::json(Flight::maintenanceRecordService()->getById($id));
});

Flight::route('POST /maintenance-records', function () {
    Flight::json(Flight::maintenanceRecordService()->add(Flight::request()->data->getData()), 201);
});

// Every field can be corrected, machine and user included: nothing else depends on them.
Flight::route('PUT /maintenance-records/@id:[0-9]+', function ($id) {
    Flight::json(Flight::maintenanceRecordService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /maintenance-records/@id:[0-9]+', function ($id) {
    Flight::maintenanceRecordService()->delete($id);
    Flight::json(['message' => 'Maintenance record deleted.']);
});
