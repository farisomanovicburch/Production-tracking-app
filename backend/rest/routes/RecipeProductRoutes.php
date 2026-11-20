<?php
// Same shape as the item routes. PUT changes only is_default; another product or recipe is another link.
Flight::route('GET /recipes/@id:[0-9]+/products', function ($id) {
    Flight::json(Flight::recipeProductService()->getByRecipe($id));
});

Flight::route('POST /recipes/@id:[0-9]+/products', function ($id) {
    Flight::json(Flight::recipeProductService()->addToRecipe($id, Flight::request()->data->getData()), 201);
});

Flight::route('PUT /recipe-products/@id:[0-9]+', function ($id) {
    Flight::json(Flight::recipeProductService()->update(Flight::request()->data->getData(), $id));
});

Flight::route('DELETE /recipe-products/@id:[0-9]+', function ($id) {
    Flight::recipeProductService()->delete($id);
    Flight::json(['message' => 'Product link removed.']);
});
