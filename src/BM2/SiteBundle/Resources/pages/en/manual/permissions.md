Might & Fealty contains a powerful permission system that allows free-form definition of government system and delegation of responsibilities. It works at several levels, which share the basic functionality.


Realm Permissions
-----------------
The ruler of a realm can define realm positions (see [others]) that can be granted authority to change aspects of the realm, such as its laws or diplomacy. If several people hold a position or the same permission is granted to several roles, everyone who holds such a position can use these powers.



Settlement Permissions
----------------------
The lords of [settlements] also define who may use the infrastructure of their estates. This way they cannot only instruct the guards who to allow through the gates, but also who may make use of the various buildings to resupply or even take soldiers from the local militia.

It also goes one step further, allowing the lord to share his powers with trusted vassals who can then, in his name, order construction works, recruit new soldiers or manage trade.

Some permissions can also be limited in amount, setting a daily limit. For example, just to be safe, you might want to restrict someone who is allowed to recruit new soldiers to a reasonable amount per day so he cannot turn on you and destroy your estate by recruiting every able-bodied man, woman and child into military service, leaving nobody to attend the fields.

* Construct Place Inside - Allows the creation of new Places of Interest within the settlement.
* Construct Place Outside - Allows the creation of new Places of Interest outside the settlement in the larger region.
* Embark at Docks - Allows embarkation at docks, if present in the region.
* Enter Location - Allows entry and exit when the settlement isn't under siege.
* Manage Construction - Allows the construction and deconstruction of buildings in the settlement.
* Manage Recruitment - Allows recruiting new soldiers and disband existing ones.
* Manage Trade - Allows the management of trades into and out of the settlement.
* Manage Units - Allows all management of Units. This also implies "Manage Recruitment".
* Mobilize Troops - *Not currently implemented*
* Resupply - Allows characters to resupply their soldiers from the settlement store.
* Update Description - Allows updating of the settlement's description.


### Permission Lists ###
To make permissions easier to manage, especially across several settlements, they are not handled on a per-character basis, but through the use of *permission lists*.

A permission list is a collection of individual characters and/or realms that are either allowed or disallowed certain actions. They are checked from top to bottom until a match is found. Lists are defined once and can then be used as often as you want. One especially cool feature of lists is that they can expand upon other lists, so a realm ruler could define a global list for his realm and everyone can use it, though they can also add their own exceptions.

Though it seems complicated at first, using these lists is actually quite easy once you've done it a few times.

For example, if you want to allow everyone from your realm to resupply at your estate, except for pesky Alice who you just don't like, you would define a list that looks like this:

* *not allowed* - Alice
* *allowed* - (your realm)

When checking for permissions, Alice would match the first check and thus be denied, while everyone else from your realm would match the 2nd check and thus be allowed, and everyone else would not match anything and thus be denied (the default).
