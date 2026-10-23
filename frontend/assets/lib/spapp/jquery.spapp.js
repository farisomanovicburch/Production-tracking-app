/*!
 * spapp - simple single page jQuery application library
 * Source: https://github.com/amiletti/spapp (master branch, last updated 2024-03-06)
 * Author: amiletti. The repository has no licence file; it is used here because
 * the IT 2001 Web Programming course teaches it.
 *
 * Modified copy of assets/js/jquery.spapp.js. Changes made for this project:
 * - routeChange takes the route name from the part of the hash before "?", so
 *   "#run-detail?id=17" opens the "run-detail" view and the record id stays in
 *   the address. The original used the whole hash, so jQuery threw a selector
 *   error on "?" and the wrong page stayed visible.
 * - routeChange marks the shown section with the class "spapp-active".
 *   spapp.css shows sections by that class instead of :target, which can only
 *   match a hash that is exactly a section id.
 */
(function($) {

  $.spapp = function(options) {

    // set config and routes
    var config, routes = {};

    config = $.extend({
      defaultView  : $("main#spapp > section:last-child").attr("id"),
      templateDir  : './tpl/',
      pageNotFound : false
    }, options );

    $("main#spapp > section").each(function(k, e) {
      var elm = $(this);
      routes[elm.attr("id")] = {
        view     : elm.attr("id"),
        load     : elm.data("load"),
        onCreate : function() { },
        onReady  : function() { }
      }
    });
    // update rotues programatically
    this.route = function(options) { $.extend(routes[options.view], options); }

    // manage hash change
    var routeChange = function() {
      var id    = location.hash.slice(1).split('?')[0];
      var route = routes[id];
      var elm   = $("#"+id);

      if( ! elm || ! route) {
        if(config.pageNotFound) {
          window.location.hash = config.pageNotFound;
          return;
        }
        console.log(id+" not defined");
        return;
      }
      // Shown by class rather than CSS :target, which cannot match "#run-detail?id=17".
      // Done before loading so the new view replaces the old one at once, even on a first visit.
      $("main#spapp > section").removeClass("spapp-active");
      elm.addClass("spapp-active");

      if(elm.hasClass("spapp-created")) {
        route.onReady();
      } else {
        elm.addClass("spapp-created");
        if( ! route.load) {
          route.onCreate();
          route.onReady();
        } else {
          elm.load(config.templateDir+route.load, function() {
            route.onCreate();
            route.onReady();
          });
        }
      }
    }

    // and run
    this.run = function() {
      window.addEventListener('hashchange', function() { routeChange(); });
      if( ! window.location.hash) { window.location.hash = config.defaultView; } else { routeChange(); }
    }

    return this;
  };
 
}(jQuery));