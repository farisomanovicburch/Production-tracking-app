<?php
// Listed and added through the recipe (its id is in the URL), edited and deleted through the item's own id.
Flight::route('GET /recipes/@id:[0-9]+/items', function ($id) {
    Flight::json(Flight::recipeItemService()->getByRecipe($id));
});

Flight::route('POST /recipes/@id:[0-9]+/items', function ($id) {
    Flight::json(Flight::recipeItemService()->addToRecipe($id, Flight::request()->data->getData()), 201);
});

Flight::route('PUT /recipe-items/@id:[0-9]+', function ($id) {
    Flight::json(Flight::recipeItemService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /recipe-items/@id:[0-9]+', function ($id) {
    Flight::recipeItemService()->delete($id);
    Flight::json(['message' => 'Recipe item deleted.']);
});
