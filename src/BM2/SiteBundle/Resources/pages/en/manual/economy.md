The economy of Might & Fealty consists of everything being produced and consumed within its settlements, from the tiniest
village to the largest city.


Food and Population
-------------------

The first level of economic activity is that everyone needs to eat. In a medieval society, most peasants will work in the fields,
or herding livestock or in other words: Producing food or food-products. The amount of food, in return, also determines the
size of the settlement. With a surplus of food, a settlement will grow as more people can be fed, more children are born and
more peasants move in from nearby settlements with less food. With a food deficit, people will starve and/or leave and population
will drop.

Since food production is related to population, and population is related to available food, these two are linked in a closed loop.  
However, since the relation is non-linear, a trend is never infinite and a settlement will always stabilize at a new equilibrium. So if you add some food to a village by trade, its population will grow, producing more food, making it grow a bit further, but it will stop after a while and reach a new, stable level. Same in the other direction, when food is lacking, population drops, leading to less food being grown, and thus less population, but the downward trend will also stop at a new, stable level.

Food production can change when peasants are sent to work on construction projects, for example. It also depends on economic security, see [settlements].


Workforce
---------

Every peasant in your settlement is working somewhere. Three basic areas of work are construction, production and local economy.

Construction is what you order in new buildings, roads or features. The workers building those will not be available for the local economy while the construction is ongoing, and once it is finished will automatically return to the local economy. You freely set the amounts here.

Production is the people employed in existing, active buildings. So if you build a blacksmith, it won't magically turn out tools and weapons once done, it actually needs a smith and some apprentices to work. The amount of workers required depends on the building and the settlement size. In a large city, the blacksmith is not one, but more like "blacksmith street", and thus there will be several master smiths and a few dozen apprentices working for them, while in a small village you might well have just the smith and his son.  
These amounts are actually based on historical data, one major source is ["Medieval Demographics made easy"](http://www222.pair.com/sjohn/blueroom/demog.htm).

The local economy is everyone not working directly for you, peasants going about their daily business.


Local Economy
-------------

The economy of a settlement consists of more than just food, of course. Not everyone works in the fields. Depending on the regional conditions, there may also be people chopping wood in the forest or mining ore. Every region has a unique local economy and will produce a base amount of food, wood and metal (though the later two can be zero, and in fact for metal quite often it is).

Settlements also produce finished goods, but these do not depend on the region type, only on the settlement size and buildings. Goods is simply a catch-all term for clothes, tools, furniture and everything else that peasants buy and sell.

Wealth, meanwhile, is an equally abstract term for jewellery, gold coins, luxury items and other money-equivalents. It will only be produced in towns and cities after some high-end buildings have been constructed.

For roleplay's sake, it's planned to make it so that each realm, through their laws, can define what a single unit of each resource actually is (TODO).


Resource Demands
----------------

Every resource is required for something. Food is necessary to feed the peasants as well as any militia or troops visiting the settlement. The other resources are used for construction and production of buildings, though they differ in how they are required. Wood, for example, is used mostly for construction while metal is used mostly for production of weapons and armor. Wealth is mainly required for high-tier buildings.

The amount of resources required depends on the building and scales with population size. The blacksmith, for example, will need a supply of 17 units of metal in a 1000 people town, but 31 units in a 2500 people town, while the Armorer needs 14 and 25. But don't worry about the precise numbers, they are just to illustrate the point. Your settlement will always self-balance as much as it can.

All the number values are *constant daily amounts*, meaning that if the game interface shows "X supply" or "X demand" or "X trade", what X means is units *per day, every day*. If it says "50 food" that means 50 units of food are produced or consumed every day. If it says "20 metal" for a trade it means 20 metal is traded every day. It's a very simple concept once you understand that it refers not to storage sizes, but to flow. Excess resources are stored to satisfy temporary shortages.


Resource Shortages
------------------

If there is not enough of a resource available, consequences follow. For food, the population will shrink. For all other resources, construction and production requiring that resource will slow down. You see, peasants are crafty. If the blacksmith has no metal, he will go out and find some. Maybe he smelts down old tools, maybe he buys metal from travelling salesmen, who knows? But the more shortage he needs to compensate for, the less time he can spend at the forge.

During times of good supply, settlements will automatically put excess resources into storage and use this storage during times of shortage. If the shortage is temporary after a long time of good supply, these storages mean it will have barely any effect. Storages are not limited in size and subject to some decay representing spoilage, rot, theft and corruption.


Economic Security
-----------------

Economic security affects resources production, and thus the surplus (or shortage) indirectly. It is explained on the [settlements] page.


Focus
-----

Buildings that produce equipment can be ordered to receive additional attention to raise production. This is called *focus*. Every level of focus will double the number of workers required in the building, while increasing production speed by 50%. There are three levels of focus, for a maximum productivity increase of a bit over three times, but at the cost of having eight times the employees in the building.

A building with set focus will display a second productivity column, showing the final productivity taking account the additional workforce. As this is a multiplier, any resource shortages a building experiences will have a strong impact on the bonus that focus gains you. While focus is a way to boost productivity higher even when resources are short, the price is considerable, and the net production gained is much lower than on a building that has its resource demands met.

Focus will automatically be lowered if the settlement experiences starvation.


Production Speed
----------------

The final production speed of a building depends on many factors. The most important ones are settlement size and resource demands. Small settlements simply cannot supply the infrastructure for high-end technology. While you can, for example, build an armorer in a large village (minimum population requirement is 900 people), do not expect him to be very productive there. While by game-mechanics he may only need metal, in reality such a workshop would also need coal and wood, skilled labor, secondary materials, tools and many other parts. We approximate these requirements by assuming that larger settlements will have them, but smaller only in a limited way.
Buildings generally reach their full effectiveness (and 100% production speed) if the settlement is twice as large as their minimum required population, and all resource demands are satisfied.

If your buildings are woefully unproductive even when they have all resources, maybe you are trying to produce chain mail and swords in a village? Do you think that would work in the real world? Maybe you should shift it to a town or city?

In Might & Fealty just because you *can* construct a building does not mean you should. This is not a construction queue rushing game where you always should build everything as soon as it becomes available. Good decisions are more important than rapid expansion.


---

Related Topics
==============
* [trade]
* [settlements]
* [looting]
* [supply]
