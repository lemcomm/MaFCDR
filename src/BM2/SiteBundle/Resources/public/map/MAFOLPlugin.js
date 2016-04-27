/**
 * @author: Teh Hsao Chai (Aliase)
 * @date: 28/03/2014 OpenLayers plugin for custom Might and Fealty OpenLayers implementations
 */

function OLPlugins(map)
{
	/**
	 * {OpenLayers.Map}. Map object that is used by Might and Fealty
	 */
	this.map = map;

	/**
	 * {OpenLayers.Control.DrawFeature}. Draw Route Control for plotting routes in Might and Fealty
	 */
	this.drawRouteControl = null;

	/**
	 * {OpenLayers.Control.ModifyRouteControl}. Modify Route Control for modifying routes in Might and Fealty
	 */
	this.modifyRouteControl = null;

	/**
	 * Denote the location the first point of the route {float} x - X coordinate {float} y - y coordinate
	 */
	this.startPosition = null;
	
	/**
	 * Object that stores the acting and scouting radius
	 */
	this.character = null;

	/**
	 * {OpenLayers.Control.Snapping}. Snap control to make routes snaps onto other vector layers during modification or drawing
	 */
	this.snapControl = null;

	/**
	 * createDrawRouteControl. This is essentially a wrapper for OpenLayers.Control.DrawFeature
	 * http://dev.openlayers.org/docs/files/OpenLayers/Control/DrawFeature-js.html
	 * 
	 * @param {OpenLayers.Layer.Vector}layer
	 *            Route layer
	 * @param {function}events.featureAdded
	 *            when a route is added
	 * @returns {OpenLayers.Control.DrawFeature}
	 */
	this.createDrawRouteControl = function(layer, events)
	{
		events = events || {};
		layer.events.on({
			sketchcomplete: events.sketchComplete
		});
		this.drawRouteControl = this.initDrawRouteControl(layer);
		this.map.addControl(this.drawRouteControl);
		return this.drawRouteControl;
	};

	/**
	 * createModifyRouteControl. Creates the modify route control
	 * 
	 * @param {OpenLayers.Layer.Vector}layer
	 *            Route layer
	 * @param {function}options.events.afterFeatureModified
	 *            when a route is modified *
	 * @param {function}options.events.onControlCreated
	 *            Callback functions to set the modify route control.
	 * @param {String}options.modifyMode
	 *            {st"travel" - During modification, unable to modify start point - {string} "trade" - During modification, unable to modify start and
	 *            end point
	 * @returns {OpenLayers.Control.ModifyRouteControl}
	 */
	this.createModifyRouteControl = function(layer, options)
	{
		options = options || {};
		layer.events.on({
			afterfeaturemodified: options.events.afterFeatureModified
		});

		this.modifyRouteControl = new OpenLayers.Control.ModifyRoute(routeLayer, {
			modifyMode: options.modifyMode,
			standalone: false
		});
		this.modifyRouteControl.deleteCodes = null; // disable default vertex delete function
		map.addControl(this.modifyRouteControl);
		
		return this.modifyRouteControl;

	};

	/**
	 * createSnapControl. This is essentially a wrapper for OpenLayers.Control.Snapping
	 * http://dev.openlayers.org/docs/files/OpenLayers/Control/Snapping-js.html
	 * 
	 * @param layer.route
	 *            the route layer
	 * @param layer.targets
	 *            the layers or objects that are the target for the route layer to snap to
	 * @param greedy:
	 *            Refer to OpenLayers API document
	 */
	this.createSnapControl = function(layers, greedy)
	{
		// layers = layers || {};
		this.snapControl = new OpenLayers.Control.Snapping({
			layer: layers.layer,
			targets: layers.targets,
			greedy: greedy
		});
		this.snapControl.activate();
		return this.snapControl;
	};

	/**
	 * 
	 */
	this.initClustering = function(layer)
	{
		layer.strategies = [new OpenLayers.Strategy.Cluster()];

	};

	/**
	 * Helper method for initializing the draw route control
	 */
	this.initDrawRouteControl = function(routeLayer)
	{
		var that = this; // Get the instance of MAFOL instance
		var drawOptions = {
			callbacks: {
				"create": function(vertex, feature)
				{
					that.drawRouteControl.insertXY(that.startPosition.x, that.startPosition.y);
					that.drawRouteControl.layer.events.triggerEvent("sketchstarted", {
						vertex: vertex,
						feature: feature
					});
					that.drawRouteControl.hoverHandler.activate();
				}
			},
			handlerOptions: {
				freehandToggle: null
			// disable freehand drawing of route
			}
		};
		return new OpenLayers.Control.DrawRoute(routeLayer, OpenLayers.Handler.Path, drawOptions);
	};
	
	this.setScoutLayer = function(scoutLayer)
	{
		this.drawRouteControl.scoutLayer = scoutLayer;
		this.modifyRouteControl.setScoutLayer(scoutLayer);
	};
}
