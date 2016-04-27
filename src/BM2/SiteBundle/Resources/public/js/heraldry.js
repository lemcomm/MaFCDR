
function displaybanner(svg, shield, shield_colour, pattern, pattern_colour, charge, charge_colour) {
	svg.clear(true);

	svg.load(basepath+"shields/"+shield+".svg", {
		addTo: false,
		changeSize: true,
		onLoad: shieldDone(svg, shield, shield_colour, pattern, pattern_colour, charge, charge_colour)
	});
}

function shieldDone(svg, shield_type, shield_colour, pattern, pattern_colour, charge, charge_colour) {
	var shield = $("#"+shield_type, svg.root());
	shield.attr('fill', shield_colour);
	shield.attr('stroke', "black");
	shield.attr('stroke-width', 2);

	var clp = svg.clipPath("boundary");
	svg.clone(clp, shield);

	if (pattern) {
		svg.load(basepath+"patterns/"+pattern+".svg", {
			addTo: true,
			changeSize: true,
			onLoad: patternDone(svg, pattern, pattern_colour, charge, charge_colour)
		});
	} else {
		loadCharge(svg, charge, charge_colour);
	}
}

function patternDone(svg, pattern, pattern_colour, charge, charge_colour) {
	pattern.attr('fill', pattern_colour);
	pattern.attr('clip-path', "url(#boundary)");
	loadCharge();
}

function loadCharge(svg, charge, charge_colour) {
	if (charge) {
		svg.load(basepath+"charges/"+charge+".svg", {
			addTo: true,
			changeSize: true,
			onLoad: chargeDone
		});
	}
}
function chargeDone() {
	var svg = $("#heraldry").svg('get');
	$("#fill", svg.root()).attr('fill', $("#heraldry_charge_colour").val());
	$("#outline", svg.root()).attr('fill', "black");
}


function getBlazon() {
	var blazon = "";

	var shield_colour = $("#heraldry_shield_colour").find(":selected").text();
	var pattern = $("#heraldry_pattern").val();
	var pattern_name = $("#heraldry_pattern").find(":selected").text();
	var pattern_colour = $("#heraldry_pattern_colour").find(":selected").text();
	var charge = $("#heraldry_charge").val();
	var charge_name = $("#heraldry_charge").find(":selected").text();
	var charge_colour = $("#heraldry_charge_colour").find(":selected").text();

	if (pattern=="quarterly" || pattern.indexOf("per_")==0) {
		blazon = pattern+" "+shield_colour+" and "+pattern_colour;
	} else {
		blazon = shield_colour;
		if (pattern!="") {
			blazon = blazon+", "+add_article(pattern_name)+" "+pattern_name+" "+pattern_colour;
		}
	}
	if (charge!="") {
		blazon = blazon+", ";
		if (pattern!="") {
			blazon = blazon+"over all ";
		}
		blazon = blazon+", "+add_article(charge_name)+" "+charge_name+" "+charge_colour;
	}
	$("#blazon").html(blazon);
}

function validateUnique() {
	$.get("{{ path("bm2_site_heraldry_validate") }}", {
		shield: $("#heraldry_shield_colour").val(),
		pattern: $("#heraldry_pattern").val(),
		patterncolour: $("#heraldry_pattern_colour").val(),
		charge: $("#heraldry_charge").val(),
		chargecolour: $("#heraldry_charge_colour").val()
	}, function(data){
		DesignValidated(data);
	});
}

function DesignValidated(result) {
	if (result===true || result=="true") {
		$("#cansave").show();
	} else {
		$("#msglist").append("<li>{{ 'warning.taken'|trans({},"heraldry") }}</li>");
		$("#messages").show();
	}
	$("#busy").hide();
}


function add_article(pattern) {
	if (pattern.search(/^aeiou.*/)==-1) {
		return "a";
	} else {
		return "an";
	}
}

{% endblock %}
