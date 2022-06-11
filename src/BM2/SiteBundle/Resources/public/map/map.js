/*
	OpenLayers Interface for Might & Fealty
	(C)2013-2022 by Andrew Gwynn <andrew@lemuriacommunity.org>
	Originally created by Tom Vogt <tom@lemuria.org>
	All Rights Reserved
*/

var map;
var geojson_format = new OpenLayers.Format.GeoJSON();
var origin = null;
var route = new OpenLayers.Geometry.LineString();
var pastroute = new OpenLayers.Geometry.LineString();
var pastRouteFeature = null;
var routelayer;
var placerlayer;
var roadlayer;
var errorlayer=null;
var usedroadslayer=null;
var hoverSelectPlace; var clickSelectPlace; var hoverSelectChars; var clickSelectChars; var clickSelectMarker;
var clickSelectOffer;
var drawRoute;
var drawPoint;
var outline_layer = 2;
var placer = "";

// get environment
var env = 'prod';
var regex = new RegExp("/app_([a-z]+)\.php/");
var results = regex.exec( window.location.href );
if (results != null) {
	var env = results[1];
}

if (location.protocol === 'https:') {
	var security = 'https:';
	var url = window.location.href;
	url = url.replace("https://", "");
} else {
	var security = 'http:';
	var url = window.location.href;
	url = url.replace("http://", "");
}

var urlExplode = url.split("/");
var host = urlExplode[0];

if (env == 'prod')  {
	var basepath = security+"//"+host+"/en/map/";
} else {
	var basepath = security+"//"+host+"/app_"+env+".php/en/map/";
}

var tilecache_url = security+"//maps.mightandfealty.com/tilecache";
var imgpath = security+"//"+host+"/bundles/bm2site/images/";
var loadimg = imgpath+'loader.png';
var tooltip;
// FIXME: these should be taken from the database!
var actdistance=200;
var spotdistance=250;
var spot_tower = 4000;
//var globalscale=100000;
var globalscale = 1;
var minScaleMultiplier = 50000;
var settlemenSizeDiv = 4; // higher values = less/smaller scale!

var my_x;
var my_y;

if (typeof mapstrings == "undefined") {
	var mapstrings = {
		'map': "Abstract Map",
		'graphmap': "Graphical Map",
		'settlements': "Settlements",
		'realms': "Sovereign Realms",
		'empires': "Empires",
		'kingdoms': "Kingdoms",
		'principalities': "Principalities",
		'duchies': "Duchies",
		'marches': "Marches",
		'counties': "Counties",
		'baronies': "Baronies",
		'poi': "Points of Interest",
		'cultures': "Cultures",
		'features': "Features",
		'markers': "Markers",
		'placer': "Placement",
		'roads': "Roads",
		'chars': "Characters",
		'route': "Route Plot",
		'invalid': "Invalid Route",
		'usedroads': "Road Travel",
		'you': "Your Location",
		'area': "Local Area"
	};
}


function mapinit(divname, showswitcher, mode, keepsquare){
	var mapBounds = new OpenLayers.Bounds(0.0, 0.0, 512000.0, 512000.0);
//	var scales = [17300, 34600, 69200, 138400, 276800, 553600, 1107200];

	// avoid pink tiles
	OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
	OpenLayers.Util.onImageLoadErrorColor = "transparent";

	var options = {
		controls: [],
		maxExtent: mapBounds,
		numZoomLevels: 10,
//		scales: scales,
		units: 'm'
	};
	map = new OpenLayers.Map(divname, options);

	var base_layer = new OpenLayers.Layer.WMS(mapstrings.map,
	   tilecache_url,  {
	   layers: 'basic',
	   format: 'image/png',
	});
	map.addLayer(base_layer);
	map.setLayerIndex(base_layer, 0);

	map.zoomToExtent(mapBounds);
	map.zoomIn();
	map.zoomIn();

	// if you wonder why we put these here instead of more logically towards the end like
	// all the examples do: Well, it turns out that they fuck up some (but not all) draw controls
	// if you init them afterwards, for unknown reasons and with no error message
	// putting them here seems to solve things. Great event handling, isn't it? If someone knows
	// of an OpenLayers alternative that doesn't suck or has ceased development, let me know
	// at tom@lemuria.org - I'd really, really, REALLY love to ditch this piece of crap
	map.addControl(new OpenLayers.Control.PanZoomBar());
	map.addControl(new OpenLayers.Control.Navigation());

	map.addControl(new OpenLayers.Control.MousePosition());
	map.addControl(new OpenLayers.Control.Scale());
	map.addControl(new OpenLayers.Control.ScaleLine());


	switch (mode) {
		case 'start':
			addsettlements(mode)
			addrealms();
			addroads();
			break;
		case 'featureconstruction':
		case 'setmarker':
			addsettlements();
			addrealms();
			addroads();
			addfeatures();
			addmarkers();
			addplacer(mode);
		default:
			addsettlements();
			addrealms();
			addroads();
			addfeatures();
			addmarkers();
	}

	add_poi();

	var switcher = new OpenLayers.Control.LayerSwitcher({
		'div': OpenLayers.Util.getElement('switcher')
	});
	map.addControl(switcher);
	showswitcher = typeof showswitcher !== 'undefined' ? showswitcher : true;
	if (showswitcher) {
		switcher.maximizeControl();
	} else {
		switcher.minimizeControl();
	}
	resize(keepsquare);
}


function set_char_location(x,y,addorigin,act,spot) {
	origin = new OpenLayers.Geometry.Point(x,y);
	if (addorigin) {
		route.addPoint(origin);
	}
	addrouting(x,y);
	if (act) {
		actdistance = act;
	}
	if (spot) {
		spotdistance = spot;
	}
	addmylocation(x,y);
	zoomto(x,y,3);
}

function set_char_path(completed, future, progress) {
	/* TODO: past route */
	if (completed) {
		$.each(completed.coordinates, function(index, value){
			pastroute.addPoint(new OpenLayers.Geometry.Point(value[0],value[1]));
		});
	}
	if (future) {
		$.each(future.coordinates, function(index, value){
			route.addPoint(new OpenLayers.Geometry.Point(value[0],value[1]));
		});
	}
	routelayer.redraw();
	updateroutelength();
}

function updateroutelength() {
	$("#routelength").html(Math.round(route.getLength()*globalscale));
}

function zoomto(x,y,zoom) {
	map.setCenter(new OpenLayers.LonLat(x,y), zoom);
}


function add_poi() {
	var style = new OpenLayers.Style({
		label: '${name}',
		fontSize: "${poiNameSize}",
		fontColor: "#f0f0c0",
		fontWeight: "bold",
		fill: false,
		stroke: false
	}, {context: zoomSupport});


	var layer = new OpenLayers.Layer.Vector(mapstrings.poi, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('poi', mapstrings.poi); },
			'loadend': function(event){ loader_off('poi'); }
		},
		strategies: [new OpenLayers.Strategy.BBOX()],
		maxScale: 10*minScaleMultiplier,
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=poi",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 150);
}

function addroads() {
	var style = new OpenLayers.Style({
		'strokeColor': '${roadColour}',
		'strokeWidth': '${roadWidth}',
		'strokeOpacity': '${roadOpacity}',
		'strokeDashstyle': '${roadStyle}'
	}, {context: zoomSupport});


	roadlayer = new OpenLayers.Layer.Vector(mapstrings.roads, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('roads', mapstrings.roads); },
			'loadend': function(event){ loader_off('roads'); }
		},
		strategies: [new OpenLayers.Strategy.BBOX()],
		minScale: 15*minScaleMultiplier,
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=roads",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	roadlayer.setVisibility(true);
	map.addLayer(roadlayer);
	map.setLayerIndex(roadlayer, 10);
}

function addfeatures() {
	var style = new OpenLayers.Style({
		externalGraphic: '${featureIcon}',
		graphicYOffset: '${featureOffset}',
		pointRadius: '${featureSize}',
		label: '${featureName}',
		labelAlign: 'ct',
		fontSize: "11px"
	}, {context: zoomSupport});


	var featureslayer = new OpenLayers.Layer.Vector(mapstrings.features, {
		renderers: ["SVG2", "VML", "Canvas"], // SVG renderer broken for these - yay!
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('features', mapstrings.features); },
			'loadend': function(event){ loader_off('features'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		minScale: 6*minScaleMultiplier,
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=features",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	featureslayer.setVisibility(true);
	map.addLayer(featureslayer);
	map.setLayerIndex(featureslayer, 25);
}

function addmarkers() {
	var style = new OpenLayers.Style({
		externalGraphic: '${markerIcon}',
		pointRadius: '${markerSize}',
	}, {context: zoomSupport});


	var markerslayer = new OpenLayers.Layer.Vector(mapstrings.markers, {
		renderers: ["SVG2", "VML", "Canvas"], // SVG renderer broken for these - yay!
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('markers', mapstrings.markers); },
			'loadend': function(event){ loader_off('markers'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		minScale: 20*minScaleMultiplier,
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=markers",
			format: new OpenLayers.Format.GeoJSON()
		})
	});

	clickSelectMarker = new OpenLayers.Control.SelectFeature(markerslayer, {
		hover: false,
		highlightOnly: true,
		renderIntent: "temporary",
		eventListeners: {
			featurehighlighted: MarkerSelect,
		}
	});
	map.addControl(clickSelectMarker);
	clickSelectMarker.activate();

	markerslayer.setVisibility(true);
	map.addLayer(markerslayer);
	map.setLayerIndex(markerslayer, 20);
}

function addrealms() {
	var style = new OpenLayers.Style({
		strokeColor: '#f00000',
		strokeWidth: '${realmBorderWidth}',
		label: '${name}',
		fontSize: "${realmNameSize}",
		fontColor: "#fff000",
		fillColor: "${colour_hex}",
		fillOpacity: 0.65
	}, {context: zoomSupport});


	var layer = new OpenLayers.Layer.Vector(mapstrings.realms, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.realms); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 100);


	var layer = new OpenLayers.Layer.Vector(mapstrings.empires, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.empires); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=7",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 99);

	var layer = new OpenLayers.Layer.Vector(mapstrings.kingdoms, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.kingdoms); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=6",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 98);

	var layer = new OpenLayers.Layer.Vector(mapstrings.principalities, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.principalities); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=5",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 97);

	var layer = new OpenLayers.Layer.Vector(mapstrings.duchies, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.duchies); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=4",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 96);

	var layer = new OpenLayers.Layer.Vector(mapstrings.marches, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.marches); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=3",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 95);

	var layer = new OpenLayers.Layer.Vector(mapstrings.counties, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.counties); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=2",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 94);

	var layer = new OpenLayers.Layer.Vector(mapstrings.baronies, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('realms', mapstrings.baronies); },
			'loadend': function(event){ loader_off('realms'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=realms&mode=1",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 93);
}



function addsettlements(mode) {
	var styleMap = new OpenLayers.StyleMap ({
		"default": new OpenLayers.Style({
			strokeColor: '${settlementStrokeColor}',
			strokeWidth: '${settlementStrokeWidth}',
			fillColor: '${settlementFillColor}',
			fontColor: '#dddddd',
			label: '${settlementName}',
			labelAlign: 'ct',
			pointRadius: '${settlementSize}'
		}, {context: zoomSupport}),
		"temporary": new OpenLayers.Style({
			strokeColor: '#ee8',
			fillColor: '#ffa',
		}, {context: zoomSupport})
	});

	var myurl = basepath+"data?type=settlements";
	var myMinScale = 15*minScaleMultiplier;
	if (mode) {
		myurl = myurl+"&mode="+mode;
		myMinScale = 50*minScaleMultiplier;
	}
	var layer = new OpenLayers.Layer.Vector(mapstrings.settlements, {
		renderers: ["SVG2", "VML", "Canvas"],
		styleMap: styleMap,
		eventListeners: {
			'loadstart': function(event){ loader_on('settlements', mapstrings.settlements); },
			'loadend': function(event){ loader_off('settlements'); }
		},
		strategies: [new OpenLayers.Strategy.BBOX()],
		minScale: myMinScale,
		protocol: new OpenLayers.Protocol.HTTP({
			url: myurl,
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(true);
	map.addLayer(layer);
	map.setLayerIndex(layer, 20);

	hoverSelectPlace = new OpenLayers.Control.SelectFeature(layer, {
		hover: true,
		highlightOnly: true,
		renderIntent: "temporary",
		eventListeners: {
			featurehighlighted: SettlementSelect,
		}
	});
	map.addControl(hoverSelectPlace);
	clickSelectPlace = new OpenLayers.Control.SelectFeature(layer, {
		hover: false,
		highlightOnly: true,
		renderIntent: "temporary",
		eventListeners: {
			featurehighlighted: SettlementSelect,
		}
	});
	map.addControl(clickSelectPlace);
	clickSelectPlace.activate();

	$("#sd").dialog({
		autoOpen: false,
		width: "25em",
		position: { my: 'right bottom', at: 'right bottom', of: $('#sd_anchor') }
	});


	// cultures

	var style = new OpenLayers.Style({
		strokeWidth: 0.5,
		strokeColor: "${colour_hex}",
		strokeOpacity: 0.4,
		fillColor: "${colour_hex}",
		fillOpacity: 0.8
	}, {context: zoomSupport});

	var layer = new OpenLayers.Layer.Vector(mapstrings.cultures, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
		eventListeners: {
			'loadstart': function(event){ loader_on('cultures', mapstrings.cultures); },
			'loadend': function(event){ loader_off('cultures'); }
		},
		strategies: [new OpenLayers.Strategy.BBOX()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=cultures",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(false);
	map.addLayer(layer);
	map.setLayerIndex(layer, 110);
}

function SettlementSelect(evt) {
	feature = evt.feature;

	$("#sd").dialog("option", "title", "...");
	$("#sd").html('<center><img src="'+loadimg+'"/></center>');
	$("#sd").dialog("option", "buttons", null);
	$.get(basepath+"details/settlement/"+feature.attributes.id, function(data){
		$("#sd").html(data);
		$("#sd").dialog("option", "title", $("#sd_name").html());
		$("#sd_name").hide();
		if (typeof startbutton != "undefined") {
			$("#sd").dialog("option", "buttons", [
				{ text: $("#sd_details a").html(), click: function() { window.location.href = $("#sd_details a").attr("href"); } },
				{ text: startbutton, click: function() { $("#form_map").submit(); } },
			] );
			var id = $("#sd_id").html();
			$("#form_settlement_id").val(id);
		} else {
			$("#sd").dialog("option", "buttons", [
				{ text: $("#sd_details a").html(), click: function() { window.location.href = $("#sd_details a").attr("href"); } }
			] );
		}
		$("#sd_details").hide();
		if (! $("#sd").dialog("isOpen")) {
			$("#sd").dialog("open");
		}
	});
}
function MarkerSelect(evt) {
	feature = evt.feature;

	$("#sd").dialog("option", "title", mapstrings.markers);
	$("#sd").html('<center><img src="'+loadimg+'"/></center>');
	$("#sd").dialog("option", "buttons", null);
	$.get(basepath+"details/marker/"+feature.attributes.id, function(data){
		$("#sd").html(data);
		$("#sd").dialog("option", "title", $("#sd_name").html());
		$("#sd_name").hide();
		$("#sd_details").hide();
		if (! $("#sd").dialog("isOpen")) {
			$("#sd").dialog("open");
		}
	});
}


function Unselect(e) {
	$("#sd").dialog("close");
}


function addcharacters() {
	var styleMap = new OpenLayers.StyleMap ({
		"default": new OpenLayers.Style({
			externalGraphic: imgpath+'marker-'+'${markerColour}'+'.png',
			graphicOpacity: '${markerOpacity}',
			graphicWith: 32,
			graphicHeight: 32,
			graphicXOffset: -4,
			graphicYOffset: -30,
			strokeWidth: 2,
			strokeColor: '#f06000',
			strokeOpacity: 0.666,
			strokeDashstyle: 'dot'
		}, {context: zoomSupport}),
		"temporary": new OpenLayers.Style({
			externalGraphic: imgpath+'marker-yellow.png',
			/* the below doesn't actually work because they are two separate features :-( */
			strokeWidth: 3,
			strokeColor: '#ff6600',
			strokeOpacity: 0.75,
			strokeDashstyle: 'solid'
		}, {context: zoomSupport})
	});


	var layer = new OpenLayers.Layer.Vector(mapstrings.chars, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: styleMap,
		eventListeners: {
			'loadstart': function(event){ loader_on('chars', mapstrings.chars); },
			'loadend': function(event){ loader_off('chars'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		minScale: 6*minScaleMultiplier,
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=characters",
			format: new OpenLayers.Format.GeoJSON()
		})
	});
	layer.setVisibility(true);
	map.addLayer(layer);
	map.setLayerIndex(layer, 30);

	hoverSelectChars = new OpenLayers.Control.SelectFeature(layer, {
		hover: true,
		highlightOnly: true,
		renderIntent: "temporary",
		eventListeners: {
			featurehighlighted: CharacterSelect,
		}
	});
	map.addControl(hoverSelectChars);
	clickSelectChars = new OpenLayers.Control.SelectFeature(layer, {
		hover: false,
		highlightOnly: true,
		renderIntent: "temporary",
		eventListeners: {
			featurehighlighted: CharacterSelect,
		}
	});
	map.addControl(clickSelectChars);
	clickSelectChars.activate();
}

function CharacterSelect(evt) {
	feature = evt.feature;

	$("#sd").dialog("option", "title", "");
	$("#sd").html("..."); /* TODO: put a spinner here */
	$("#sd").dialog("option", "buttons", null);
	$.get(basepath+"details/character/"+feature.attributes.id, function(data){
		$("#sd").html(data);
		$("#sd").dialog("option", "title", $("#sd_name").html());
		$("#sd_name").hide();
		$("#sd").dialog("option", "buttons", [ { text: $("#sd_details a").html(), click: function() { window.location.href = $("#sd_details a").attr("href"); } } ] );
		$("#sd_details").hide();
		if (! $("#sd").dialog("isOpen")) {
			$("#sd").dialog("open");
		}
	});

}


function scale() {
	return map.getResolution()*globalscale;
}

var zoomSupport = {
	roadWidth: function(feature) {
		var base = 10;
		switch (feature.attributes.quality) {
			case 0: base=6; break;
			case 1: base=8; break;
			case 2: base=12; break;
			case 3: base=16; break;
			case 4: base=20; break;
			case 5: base=24; break;
		}
		var width = base/Math.sqrt(scale());
		return width;
	},
	roadColour: function(feature) {
		switch (feature.attributes.quality) {
			case 0: return '#605020';
			case 1: return '#d0a040';
			case 2: return '#b09040';
			case 3: return '#805020';
			case 4: return '#705040';
			case 5: return '#606066';
		}
		return '#ff0000';
	},
	roadOpacity: function(feature) {
		switch (feature.attributes.quality) {
			case 0: return 0.5;
			case 1: return 0.5;
			case 2: return 0.75;
			case 3: return 0.9;
		}
		return 1.0;
	},
	roadStyle: function(feature) {
		if (feature.attributes.quality>0) {
			return 'solid';
		} else {
			return 'longdash';
		}
	},

	featureIcon: function(feature) {
		// FIXME: this should read from the database!
		if (feature.attributes.active) {
			switch (feature.attributes.type) {
				case 'bridge':			return imgpath+'rpg_map/bridge_stone1.svg';
				case 'tower':			return imgpath+'rpg_map/watch_tower.svg';
				case 'borderpost':		return imgpath+'rpg_map/sign_post.svg';
				case 'signpost':		return imgpath+'rpg_map/sign_crossroad.svg';
				case 'docks':			return imgpath+'rpg_map/docks.svg';
				case 'battle':  		return imgpath+'ryanlerch_sword_battleaxe_shield.svg';
				case 'ship': 	 		return imgpath+'mystica_pirate-ship.svg';

				// dungeons
				case 'cave':			return imgpath+'rpg_map/cave_entrance.svg';
				case 'wild':			return imgpath+'rpg_map/obelisk.svg';
				case 'ruin':			return imgpath+'rpg_map/ruins.svg';
				case 'dungeon':			return imgpath+'rpg_map/ruins.svg';
				case 'glade':			return imgpath+'rpg_map/hill1.svg';
				case 'lab':			return imgpath+'rpg_map/maze.svg';
				case 'mausoleum':		return imgpath+'rpg_map/ruins.svg';
				case 'hold':			return imgpath+'rpg_map/ruins.svg';
				case 'citadel': 		return imgpath+'rpg_map/ruins.svg';
				case 'roguefort':		return imgpath+'rpg_map/ruins.svg';
				case 'flooded':			return imgpath+'rpg_map/obelisk.svg';
				case 'shipgrave':		return imgpath+'rpg_map/shipwreck.svg';

				// places
				case 'academy':			return imgpath+'rpg_map/university.svg';
				case 'arena':			return imgpath+'rpg_map/statue.svg';
				case 'capital':			return imgpath+'rpg_map/fountain.svg';
				case 'castle':			return imgpath+'rpg_map/fort.svg';
				case 'cave':			return imgpath+'rpg_map/cave_entrace.svg';
				case 'fort':			return imgpath+'rpg_map/city.svg';
				case 'home':			return imgpath+'rpg_map/fountain.svg';
				case 'inn':			return imgpath+'rpg_map/inn.svg';
				case 'library':			return imgpath+'rpg_map/university.svg';
				case 'monument':		return imgpath+'rpg_map/statue.svg';
				case 'plaza':			return imgpath+'rpg_map/arch.svg';
				case 'portal':			return imgpath+'rpg_map/magic_stones.svg';
				case 'tavern':			return imgpath+'rpg_map/tavern.svg';
			}
		} else {
			switch (feature.attributes.type) {
				case 'bridge':			return imgpath+'rpg_map/bridge_stone1_outline.svg';
				case 'tower':			return imgpath+'rpg_map/watch_tower_outline.svg';
				case 'borderpost':	return imgpath+'rpg_map/sign_post_outline.svg';
				case 'signpost':		return imgpath+'rpg_map/sign_crossroad_outline.svg';
				case 'docks':			return imgpath+'rpg_map/docks_outline.svg';
				case 'battle':  		return imgpath+'ryanlerch_sword_battleaxe_shield.svg';
				case 'ship': 	 		return imgpath+'mystica_pirate-ship.svg';
			}
		}
	},
	featureOffset: function(feature) {
		var base = 5;
		switch (feature.attributes.type) {
			case 'bridge':			base = 5; break;
			case 'tower':			base = 10; break;
			case 'borderpost':	base = 6; break;
			case 'signpost':		base = 4; break;
			case 'battle': 		base = 15; return (base*2 + base*5/scale())*-1;
		}
		return (base + base*10/scale())*-1.5;
	},
	featureSize: function(feature) {
		var base = 15;
		switch (feature.attributes.type) {
			case 'bridge':			base = 10; break;
			case 'tower':			base = 12; break;
			case 'borderpost':	base = 6; break;
			case 'signpost':		base = 4; break;
			case 'battle': 		base = 15; return base*2 + base*5/scale();
		}
		return base + base*10/scale();
	},
	featureName: function(feature) {
		if (scale()<30 && feature.attributes.name != null) {
			return feature.attributes.name;
		}
		return "";
	},

	markerIcon: function(feature) {
		switch (feature.attributes.type) {
			case 'enemy':  		return imgpath+'anonymous-target-with-arrow.svg';
			default:					return imgpath+'rg1024-meeting-point-in-brillant-style.svg';
		}
	},
	markerSize: function(feature) {
		var base = 5;
		return base + base*100/scale();
	},

	settlementSize: function(feature) {
		var size = 2 + Math.sqrt(feature.attributes.population/settlemenSizeDiv);
		return size*10/scale();
	},
	settlementName: function(feature) {
		if (scale()<0.002*minScaleMultiplier) {
			return feature.attributes.name;
		}
		return "";
	},
	settlementFillColor: function(feature) {
		if (feature.attributes.occupied) {
			return '#d907e0';
		} else if (feature.attributes.owned) {
			return '#c00000';
		} else {
			return '#0000c0';
		}
	},
	settlementStrokeColor: function(feature) {
		if (feature.attributes.defenses > 0 && scale() <= 100) {
			return '#000000';
		} else if (feature.attributes.occupied) {
			return '#97059c';
		} if (feature.attributes.owned) {
			return '#a00000';
		} else {
			return '#0000a0';
		}
	},
	settlementStrokeWidth: function(feature) {
		if (scale()>100) return 0;
		if (feature.attributes.defenses > 3) {
			if (scale()<20) return 4;
			if (scale()<50) return 3;
			return 2;
		} else if (feature.attributes.defenses > 2) {
			if (scale()<20) return 3;
			if (scale()<50) return 2;
			return 1;
		} else if (feature.attributes.defenses > 1) {
			if (scale()<20) return 2;
			return 1;
		} else if (feature.attributes.defenses > 0) {
			return 1;
		} else {
			return 2;
		}
	},
	markerColour: function(feature) {
		if (feature.attributes.current) {
			if (feature.attributes.family) {
				return 'blue';
			} else {
				return 'red';
			}
		} else {
			return 'black';
		}
	},
	markerOpacity: function(feature) {
		if (feature.attributes.current) {
			return 1.0;
		} else {
			return 0.5;
		}
	},
	markerStrokeWidth: function(feature) {
		return 80/scale();
	},

	actRadius: function(feature) {
		return actdistance/scale();
	},
	spotNearRadius: function(feature) {
		switch (feature.attributes.type) {
			case 'tower': 			return 0.5*spot_tower/scale();
		}
		return 0.5*spotdistance/scale();
	},
	spotFarRadius: function(feature) {
		switch (feature.attributes.type) {
			case 'tower': 			return spot_tower/scale();
		}
		return spotdistance/scale();
	},


	realmBorderWidth: function(feature) {
		return 100/scale();
	},
	realmNameSize: function(feature) {
		var size = 1000 + Math.sqrt(feature.attributes.estates)*1200;
		return size/scale();
	},

	poiNameSize: function(feature) {
		return 6000/scale();
	},
}



function addmylocation(x, y) {
	var styleMap = new OpenLayers.StyleMap(new OpenLayers.Style({}, {context: zoomSupport}));
	my_x = x;
	my_y = y;

	var lookup = {
		"location": {
			externalGraphic: imgpath+'marker-green.png',
			fillOpacity: 1.0,
			graphicWith: 32,
			graphicHeight: 32,
			graphicXOffset: -4,
			graphicYOffset: -30,
		},
		"act": {
			fillColor: '#0090e0',
			fillOpacity: 0.2,
			strokeColor: '#00a0f0',
			strokeOpacity: 0.8,
			pointRadius: '${actRadius}'
		},
		"spot_near": {
			fillColor: '#e0a000',
			fillOpacity: 0.1,
			strokeColor: '#e0e000',
			strokeOpacity: 0.5,
			pointRadius: '${spotNearRadius}'
		},
		"spot_far": {
			fillColor: '#e0b040',
			fillOpacity: 0.1,
			strokeColor: '#e0e000',
			strokeOpacity: 0.25,
			pointRadius: '${spotFarRadius}'
		},
		"tower": {
			fillColor: '#e0a000',
			fillOpacity: 0.1,
			strokeColor: '#d0d000',
			strokeOpacity: 0.3,
			pointRadius: '${spotFarRadius}'
		},
	};
	styleMap.addUniqueValueRules("default", "type", lookup);

	var layer = new OpenLayers.Layer.Vector(mapstrings.you, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: styleMap,
		eventListeners: {
			'loadstart': function(event){ loader_on('towers', mapstrings.towers); },
			'loadend': function(event){ loader_off('towers'); }
		},
		strategies: [new OpenLayers.Strategy.Fixed()],
		protocol: new OpenLayers.Protocol.HTTP({
			url: basepath+"data?type=towers",
			format: new OpenLayers.Format.GeoJSON()
		})
	});

	layer.events.register("loadend", layer, function(){
		var features = Array(3);
		features[0] = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(my_x, my_y), { type: "location" });
		features[1] = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(my_x, my_y), { type: "act" });
		features[2] = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(my_x, my_y), { type: "spot_near" });
		features[3] = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(my_x, my_y), { type: "spot_far" });
		this.addFeatures(features);
	});

	map.addLayer(layer);
	map.setLayerIndex(layer, 29); // just under other characters, so our circles don't overlay them
}


function addrouting() {
	/* drawing on the map */
	routelayer = new OpenLayers.Layer.Vector(mapstrings.route, {
		displayInLayerSwitcher: false
	});
	var routeFeature = routeline();

	pastRouteFeature = new OpenLayers.Feature.Vector(pastroute, null, {
		strokeColor: '#d0d0a0',
		strokeOpacity: 0.6,
		strokeWidth: 3
	});

	routelayer.addFeatures([routeFeature, pastRouteFeature]);
	map.addLayer(routelayer);
	map.setLayerIndex(routelayer, 35);

	var snap = new OpenLayers.Control.Snapping({
		layer: roadlayer,
		tolerance: 25
	});
	snap.activate();

	drawRoute = new OpenLayers.Control.DrawFeature(routelayer, OpenLayers.Handler.Point, {
		featureAdded: point_added
	});
	map.addControl(drawRoute);
//	drawRoute.activate();
}


function point_added(data) {
	// TODO: verify route (rivers, cliffs, etc) - right here via AJAX?
	$.each(data.geometry.getVertices(), function(){
		route.addPoint(this);
	});
	routelayer.redraw();
	updateroutelength();
}


function addplacer(mode) {
	placer = mode;
	placerlayer = new OpenLayers.Layer.Vector("Point Layer");
	map.addLayer(placerlayer);

	drawPoint = new OpenLayers.Control.DrawFeature(placerlayer, OpenLayers.Handler.Point, {
		featureAdded: placer_done
	});
	map.addControl(drawPoint);
	drawPoint.activate();
}

function placer_done(data) {
	placerlayer.removeAllFeatures();
	placerlayer.addFeatures([data]);
	placerlayer.redraw();
	$("#"+placer+"_new_location_x").val(data.geometry.x);
	$("#"+placer+"_new_location_y").val(data.geometry.y);
}

function addoutline(regionpoly, stroke_color, stroke_width, fill_color, opacity, strokestyle) {
	stroke_color = typeof stroke_color != 'undefined' ? stroke_color : '#ffff80';
	stroke_width = typeof stroke_width != 'undefined' ? stroke_width : 3;
	fill_color = typeof fill_color != 'undefined' ? fill_color : '#60d0ff';
	opacity = typeof opacity != 'undefined' ? opacity : 0.2;
	strokestyle = typeof strokestyle != 'undefined' ? strokestyle : 'solid';
	var style = new OpenLayers.Style({
		"fillColor": fill_color,
		"fillOpacity": opacity,
		"strokeColor": stroke_color,
		"strokeWidth": stroke_width,
		'strokeDashstyle': strokestyle
	});

	var poly = new OpenLayers.Geometry.fromWKT(regionpoly);
	var regionfeature = new OpenLayers.Feature.Vector(poly);
	var layer = new OpenLayers.Layer.Vector(mapstrings.area, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(style),
	});
	layer.addFeatures([regionfeature]);
	layer.setVisibility(true);
	map.addLayer(layer);
	map.setLayerIndex(layer, outline_layer);
	outline_layer = outline_layer + 1;
	map.zoomToExtent(poly.getBounds());
}




function remove_last_point(url) {
	var points = route.components.length;
	if (points<2) {
		// do nothing, this is our current location
	} else if (points<3) {
		clearRoute(url);
	} else {
		// TODO: there's also an undo() in the draw handler we could use
		var last = route.components[points-1];
		route.removePoint(last);
		routelayer.redraw();
		updateroutelength();
	}
}


function submitRoute(url) {
	var myroute = new Array();
	$.each(route.getVertices(), function(){
		myroute.push(new Array(this.x, this.y));
	});
	var enter = $("#enterdest").is(":checked");
	$.post(url, { "route": myroute, "enter":enter }, function(data){
		updateRoute(data);
	});
}

function adderrorlayer() {
	var errorStyle = {
		strokeColor: '#ff1010',
		strokeOpacity: 0.9,
		strokeWidth: 5,
		pointRadius: 5
	};

	errorlayer = new OpenLayers.Layer.Vector(mapstrings.invalid, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(errorStyle),
		displayInLayerSwitcher: false
	});
	errorlayer.setVisibility(true);
	map.addLayer(errorlayer);
	map.setLayerIndex(errorlayer, 34);
}

function addusedroadslayer() {
	var usedroadsStyle = {
		strokeColor: '#40e020',
		strokeOpacity: 0.9,
		strokeWidth: 4
	};

	usedroadslayer = new OpenLayers.Layer.Vector(mapstrings.usedroads, {
		renderers: ["SVG2", "SVG", "VML", "Canvas"],
		styleMap: new OpenLayers.StyleMap(usedroadsStyle),
		displayInLayerSwitcher: false
	});
	usedroadslayer.setVisibility(true);
	map.addLayer(usedroadslayer);
	map.setLayerIndex(usedroadslayer, 32);
}

function routeline() {
	var routeStyle = {
		strokeColor: '#f0f000',
		strokeOpacity: 0.75,
		strokeWidth: 3
	};
	return new OpenLayers.Feature.Vector(route, { displayInLayerSwitcher: false }, routeStyle);
}



function clearRoute(url) {
	if (route.components.length<2) return; // only have origin anyways, don't spam server
	$.post(url, null, function(){
		if (errorlayer) { errorlayer.removeAllFeatures(); }
		if (usedroadslayer) { usedroadslayer.removeAllFeatures(); }
		routelayer.removeAllFeatures();
		if (pastRouteFeature) {
			routelayer.addFeatures([pastRouteFeature]);
		}
		route.destroy();
		route = new OpenLayers.Geometry.LineString();
		route.addPoint(origin);
		var routeFeature = routeline();
		routelayer.addFeatures([routeFeature]);
		routelayer.redraw();
		updateroutelength();
	});
}

function resize(keepsquare) {
	var mymap = document.getElementById("map");
	mymap.style.width = $("#map").width()+"px";
	if (keepsquare) {
		mymap.style.height = $("#map").width()+"px";
	} else {
		mymap.style.height = $("#map").height()+"px";
	}
	if (map.updateSize) { map.updateSize(); };
}

function loader_on(tag, name) {
	$("#loadlist").append('<li class="red" id="loader_'+tag+'">'+name+'</li>').fadeIn(200);
}

function loader_off(tag) {
	$("#loader_"+tag).animate({color: "#3ed130"}).delay(200).fadeOut(200, function(){ $(this).remove() });
}

/*
onresize=function(){ resize(); };
*/
