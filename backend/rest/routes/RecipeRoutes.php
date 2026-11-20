<?php
Flight::route('GET /recipes', function () {
    Flight::json(Flight::recipeService()->getAll());
});

Flight::route('GET /recipes/@id:[0-9]+', function ($id) {
    Flight::json(Flight::recipeService()->getById($id));
});

Flight::route('POST /recipes', function () {
    Flight::json(Flight::recipeService()->add(Flight::request()->data->getData()), 201);
});

Flight::route('PUT /recipes/@id:[0-9]+', function ($id) {
    Flight::json(Flight::recipeService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /recipes/@id:[0-9]+', function ($id) {
    Flight::recipeService()->delete($id);
    Flight::json(['message' => 'Recipe deleted.']);
});
