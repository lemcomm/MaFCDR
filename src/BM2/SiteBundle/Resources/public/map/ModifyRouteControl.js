/**
 * @author Teh Hsao Chai (Aliase) For Might and Fealty Route modify control, extend from OpenLayers.Control.ModifyFeature control. Added function to
 *         delete vertex with a click Added function to define if the start point and or end point can be modified
 */

OpenLayers.Control.ModifyRoute = OpenLayers.Class(OpenLayers.Control.ModifyFeature, {

	/**
	 * APIProperty: documentDrag {Boolean} If set to true, dragging vertices will continue even if the mouse cursor leaves the map viewport. Default
	 * is false.
	 */
	modifyMode: null,
	
	scoutLayer: null,
	setScoutLayer: function(layer)
	{
		this.scoutLayer = layer;
		
	},
	
	removeScoutRadius: function(evt)
	{
		for (var i = 0; i < this.scoutLayer.features.length; i++)
		{
			if (this.scoutLayer.features[i].attributes.range)
				this.scoutLayer.destroyFeatures([this.scoutLayer.features[i]]);
		}
	},
	
	initialize: function(layer, options)
	{
		options = options || {};

		// set mode Might & Fealty
		this.modifyMode = options.modifyMode;

		this.layer = layer;
		this.vertices = [];
		this.virtualVertices = [];
		this.virtualStyle = OpenLayers.Util.extend({}, this.layer.style || this.layer.styleMap.createSymbolizer(null, options.vertexRenderIntent));
		this.virtualStyle.fillOpacity = 0.3;
		this.virtualStyle.strokeOpacity = 0.3;
		this.deleteCodes = [46, 68];
		this.mode = OpenLayers.Control.ModifyFeature.RESHAPE;
		OpenLayers.Control.prototype.initialize.apply(this, [options]);
		if (!(OpenLayers.Util.isArray(this.deleteCodes)))
		{
			this.deleteCodes = [this.deleteCodes];
		}
		this.layer.events.register('featuremodified', this, this.removeScoutRadius);
		// configure the drag handler
		var dragCallbacks = {
			down: function(pixel)
			{
				this.vertex = null;
				var feature = this.layer.getFeatureFromEvent(this.handlers.drag.evt);
				if (feature)
				{
					if (feature.id == this.layer.features[this.layer.features.length - 1].id)
					{
						this.handlers.hover.activate();
						log(feature.id + " " + this.layer.features[this.layer.features.length - 1].id);
					}
					this.dragStart(feature);
				}
				else if (this.clickout)
				{
					this._unselect = this.feature;
				}
			},
			move: function(pixel)
			{
				delete this._unselect;
				if (this.vertex)
				{
					this.dragVertex(this.vertex, pixel);
				}
			},
			up: function()
			{
				this.handlers.drag.stopDown = false;
				if (this._unselect)
				{
					this.unselectFeature(this._unselect);
					delete this._unselect;
				}
			},
			done: function(pixel)
			{
				if (this.vertex)
				{
					this.dragComplete(this.vertex);
					this.handlers.hover.deactivate();
					for (var i = 0; i < this.layer.features.length; i++)
					{
						if (this.layer.features[i].attributes.range)
							this.layer.destroyFeatures([this.layer.features[i]]);
					}
				}
			}
		};
		var hoverCallbacks = {
			pause: function(evt)
			{
				var center = this.map.getLonLatFromPixel(evt.xy);
				var scoutRadius = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(center.lon, center.lat), {
					type: "spot",
					range: "vision"
				});
				this.scoutLayer.addFeatures([scoutRadius]);
				
			},
			move: this.removeScoutRadius
		};
		var dragOptions = {
			documentDrag: this.documentDrag,
			stopDown: false
		};

		// configure the keyboard handler
		var keyboardOptions = {
			keydown: this.handleKeypress
		};
		var hoverOptions = {
			'delay': 500,
			'pixelTolerance': null
		};

		this.handlers = {
			keyboard: new OpenLayers.Handler.Keyboard(this, keyboardOptions),
			drag: new OpenLayers.Handler.Drag(this, dragCallbacks, dragOptions),
			hover: new OpenLayers.Handler.Hover(this, hoverCallbacks, hoverOptions)
		};
	},

	/**
	 * APIMethod: activate Activate the control. Returns: {Boolean} Successfully activated the control.
	 */
	activate: function()
	{
		this.moveLayerToTop();
		this.map.events.on({
			"removelayer": this.handleMapEvents,
			"changelayer": this.handleMapEvents,
			scope: this
		});
		this.layer.events.on({
			"featureclick": this.vertexclick,
			scope: this
		});
		return (this.handlers.keyboard.activate() && this.handlers.drag.activate() && OpenLayers.Control.prototype.activate.apply(this, arguments));
	},

	/**
	 * APIMethod: deactivate Deactivate the control. Returns: {Boolean} Successfully deactivated the control.
	 */
	deactivate: function()
	{
		var deactivated = false;
		// the return from the controls is unimportant in this
		// case
		if (OpenLayers.Control.prototype.deactivate.apply(this, arguments))
		{
			this.moveLayerBack();
			this.map.events.un({
				"removelayer": this.handleMapEvents,
				"changelayer": this.handleMapEvents,
				scope: this
			});
			this.layer.events.un({
				"featureclick": this.vertexclick,
				scope: this
			});
			this.layer.removeFeatures(this.vertices, {
				silent: true
			});
			this.layer.removeFeatures(this.virtualVertices, {
				silent: true
			});
			this.vertices = [];
			this.handlers.drag.deactivate();
			this.handlers.keyboard.deactivate();
			this.handlers.hover.deactivate();
			var feature = this.feature;
			if (feature && feature.geometry && feature.layer)
			{
				this.unselectFeature(feature);
			}
			deactivated = true;
		}
		return deactivated;
	},

	vertexclick: function(evt)
	{
		var isVertex = false;
		for (var i = 0; i < this.vertices.length; i++)
		{
			if (this.vertices[i] == evt.feature)
			{
				isVertex = true;
				break;
			}
		}
		if (isVertex)
		{
			this.layer.drawFeature(evt.feature, "select");
			var toDelete = confirm("Do you want to delete waypoint?");
			if (toDelete)
			{
				var isRemoved = evt.feature.geometry.parent.removeComponent(evt.feature.geometry);
				this.layer.events.triggerEvent("vertexremoved", {
					vertex: evt.feature.geometry,
					feature: this.feature,
					pixel: evt.xy
				});
				this.layer.drawFeature(this.feature, this.standalone ? undefined : 'select');
				this.modified = true;
				this.resetVertices();
				this.setFeatureState();
				this.onModification(this.feature);
				this.layer.events.triggerEvent("featuremodified", {
					feature: this.feature
				});

			}
		}
	},

	/**
	 * Method: collectVertices Collect the vertices from the modifiable feature's geometry and push them on to the control's vertices array.
	 */
	collectVertices: function()
	{
		this.vertices = [];
		this.virtualVertices = [];
		var control = this;
		function collectComponentVertices(geometry)
		{
			var i, vertex, component, len;
			if (geometry.CLASS_NAME == "OpenLayers.Geometry.Point")
			{
				vertex = new OpenLayers.Feature.Vector(geometry);
				vertex._sketch = true;
				vertex.renderIntent = control.vertexRenderIntent;
				control.vertices.push(vertex);
			}
			else
			{
				var numVert = geometry.components.length;
				if (geometry.CLASS_NAME == "OpenLayers.Geometry.LinearRing")
				{
					numVert -= 1;
				}
				// changed such that the first vertex, which is the current player location cannot be modified
				var start = 0;
				var end = numVert;
				if (this.modifyMode == "travel")
				{
					start++;
				}
				else if (this.modifyMode == "trade")
				{
					start++;
					end--;
				}
				for (i = start; i < end; ++i)
				{
					component = geometry.components[i];
					if (component.CLASS_NAME == "OpenLayers.Geometry.Point")
					{
						vertex = new OpenLayers.Feature.Vector(component);
						vertex._sketch = true;
						vertex.renderIntent = control.vertexRenderIntent;
						control.vertices.push(vertex);
					}
					else
					{
						collectComponentVertices(component);
					}
				}

				// add virtual vertices in the middle of each edge
				if (control.createVertices && geometry.CLASS_NAME != "OpenLayers.Geometry.MultiPoint")
				{
					for (i = 0, len = geometry.components.length; i < len - 1; ++i)
					{
						var prevVertex = geometry.components[i];
						var nextVertex = geometry.components[i + 1];
						if (prevVertex.CLASS_NAME == "OpenLayers.Geometry.Point" && nextVertex.CLASS_NAME == "OpenLayers.Geometry.Point")
						{
							var x = (prevVertex.x + nextVertex.x) / 2;
							var y = (prevVertex.y + nextVertex.y) / 2;
							var point = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(x, y), null, control.virtualStyle);
							// set the virtual parent and intended index
							point.geometry.parent = geometry;
							point._index = i + 1;
							point._sketch = true;
							control.virtualVertices.push(point);
						}
					}
				}
			}
		}
		collectComponentVertices.call(this, this.feature.geometry);
		this.layer.addFeatures(this.virtualVertices, {
			silent: true
		});
		this.layer.addFeatures(this.vertices, {
			silent: true
		});
	},

	setMap: function(map)
	{
		this.handlers.drag.setMap(map);
		this.handlers.hover.setMap(map);
		OpenLayers.Control.prototype.setMap.apply(this, arguments);
	},

	CLASS_NAME: "OpenLayers.Control.ModifyRoute"
});

OpenLayers.Control.DrawRoute = OpenLayers.Class(OpenLayers.Control.DrawFeature, {

	hoverHandler: null,
	
	scoutLayer: null,

	/**
	 * Constructor: OpenLayers.Control.DrawFeature Parameters: layer - {<OpenLayers.Layer.Vector>} handler - {<OpenLayers.Handler>} options -
	 * {Object}
	 */
	initialize: function(layer, handler, options)
	{
		OpenLayers.Control.prototype.initialize.apply(this, [options]);
		this.callbacks = OpenLayers.Util.extend({
			done: this.drawFeature,
			modify: function(vertex, feature)
			{
				this.layer.events.triggerEvent("sketchmodified", {
					vertex: vertex,
					feature: feature
				});
			},
			create: function(vertex, feature)
			{
				this.insertXY(that.noblePosition.x, that.noblePosition.y);
				this.layer.events.triggerEvent("sketchstarted", {
					vertex: vertex,
					feature: feature
				});
				this.hoverHandler.activate();
			}
		}, this.callbacks);
		this.layer = layer;
		this.handlerOptions = this.handlerOptions || {};
		this.handlerOptions.layerOptions = OpenLayers.Util.applyDefaults(this.handlerOptions.layerOptions, {
			renderers: layer.renderers,
			rendererOptions: layer.rendererOptions
		});
		if (!("multi" in this.handlerOptions))
		{
			this.handlerOptions.multi = this.multi;
		}
		var sketchStyle = this.layer.styleMap && this.layer.styleMap.styles.temporary;
		if (sketchStyle)
		{
			this.handlerOptions.layerOptions = OpenLayers.Util.applyDefaults(this.handlerOptions.layerOptions, {
				styleMap: new OpenLayers.StyleMap({
					"default": sketchStyle
				})
			});
		}
		this.handler = new handler(this, this.callbacks, this.handlerOptions);

		var hoverCallbacks = {
			pause: function(evt)
			{
				var center = this.map.getLonLatFromPixel(evt.xy);
//				var point = new OpenLayers.Geometry.Point(center.lon, center.lat);
//				var circle = OpenLayers.Geometry.Polygon.createRegularPolygon(point, 2000, 30);
//				var feature = new OpenLayers.Feature.Vector(circle);
//				feature.attributes.range = "vision";
				
				var scoutRadius = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(center.lon, center.lat), {
					type: "spot",
					range: "vision"
				});
				this.scoutLayer.addFeatures([scoutRadius]);
//				this.layer.addFeatures([feature]);
			},
			move: function(evt)
			{
				for (var i = 0; i < this.scoutLayer.features.length; i++)
				{
					if (this.scoutLayer.features[i].attributes.range)
						this.scoutLayer.destroyFeatures([this.scoutLayer.features[i]]);
				}
			}
		};

		var hoverOptions = {
			'delay': 500,
			'pixelTolerance': null
		};
		
		this.hoverHandler = new OpenLayers.Handler.Hover(this, hoverCallbacks, hoverOptions); 
	},
	
	/**
     * Method: drawFeature
     */
    drawFeature: function(geometry) {
    	this.hoverHandler.deactivate();
        var feature = new OpenLayers.Feature.Vector(geometry);
        var proceed = this.layer.events.triggerEvent(
            "sketchcomplete", {feature: feature}
        );
        if(proceed !== false) {
            feature.state = OpenLayers.State.INSERT;
            this.layer.addFeatures([feature]);
            this.featureAdded(feature);
            this.events.triggerEvent("featureadded",{feature : feature});
        }
    },
    
    setMap: function(map)
	{
		this.hoverHandler.setMap(map);
		OpenLayers.Control.prototype.setMap.apply(this, arguments);
	},

	CLASS_NAME: "OpenLayers.Control.DrawRoute"
});