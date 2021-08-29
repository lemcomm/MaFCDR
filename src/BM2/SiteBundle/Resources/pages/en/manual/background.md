There is quite a lot that goes into creating a game like Might & Fealty, and this part of the manual gives you a few of the background details and sources.


Basics
------
The world of Might & Fealty is losely based on the european middle ages, somewhere in the 15th century, between the inventions of full plate armor suits  and the emergence of gunpowder rifles. It is, however, a fantasy world, where gunpowder and other technology has never been developed (China, for example, had matchlock firearms before Europe had knightly plate armor).

By "fantasy", we mean a world that does have fantasy elements, including magic, but where these elements fade into the background. They are used mostly to explain elements that are necessary for gameplay, but violate a strong simulation - like instant delivery of messages, for example.



Settlements and Professions
---------------------------
There is a strong relation between population and buildings in this game, with minimum number of people needed, a threshold at which the people will start building something on their own and a number of workers required for each building, scaling by settlement size.

All these numbers are actually based on historical facts, like the collection of data that John Ross published in ["Medieval Demographics made easy"](http://www222.pair.com/sjohn/blueroom/demog.htm). With some modifications for gameplay purposes, density and worker numbers are fairly close to accurate.



Map Generation
--------------
One of the most time-consuming processes on the creation side in Might & Fealty is the map. When I set out, I had learnt two things from my previous game, [BattleMaster](http://battlemaster.org) regarding maps: One, it is a lot of work to create a good map by hand, two when you need one, you need it now and not in a month.

So my initial goal for Might & Fealty was to write a map generator that would automate the entire process. That didn't quite work out, but I got the most important parts automated, with the help of the fantastic article ["Polygonal Map Generation for Games"](http://www-cs-students.stanford.edu/~amitp/game-programming/polygon-map-generation/) which solved so many problems that I am mentioning it here even tough the current map generator uses none of its code and only a few of the concepts (namely voronoi cells and biome distribution by altitude and moisture/humidity).

The Might & Fealty maps are based on a fractal rough design that generates the basic shape of the land and is fine-tuned manually. A first map generator step creates the data points, some of which will later turn into settlements. A [GIS](http://www.esri.com/what-is-gis/) tool named [QuantumGIS](http://www.qgis.org/) is used to generate the voronoi cells, clean up the coastline and add rivers. I tried several automatic generation algorithms for rivers including several attempts to use flow-based pathfinding, but in the end simply drawing them in manually turned out to be the best approach with the most realistic results.

Finally, with the rivers and potential settlement locations set, the whole data set is turned over to the map generator script which calculates humidity, altitude and biome distribution. It also generates the resource distribution, based on a fractal distribution combined with the map and biome data (so metal is found in the mountains, etc.). From there, the settlement data is cleaned up, ocean cells are merged and the whole set goes back into manual processing for the graphical map, where the workflow and style is based on the fantastic [Ascensions' Atlas style](http://www.cartographersguild.com/content/116-ascensions-atlas-style-photoshop.html).
