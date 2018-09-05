### This manual page is detailing a highly experimental and incomplete feature that is in the process of being implemented. Everything on this page, while as detailed as it may be, is subject to change. ###

When [diplomacy] or [messages] cannot solve a dispute any longer, swords will. Battle is armed conflict between military forces, a single encounter on the battlefield.


Battle Preparations
-------------------
In the medieval world simulated by Might & Fealty, many concepts of modern warfare are non-existent. There is no trench warfare, no artillery and no air superiority. War is not fought by mobile infantry and Blitzkrieg maneuvers. Instead, war is quite often orchestrated, battlefields agreed upon beforehand and strategies well-known and familiar.

That said, battles are initiated by someone preparing to attack one or more opponents. This forces both sides to begin battle preparations, deploy troops, maneuver for optimal positions - a process that can take hours, and sometimes days. Many ancient and medieval battles actually worked very similarly to this, though of course for gameplay purposes the process has been abstracted and structured.

When you engage an enemy, whether attacking them on the open field or assaulting a settlement, you are beginning a timed action, with the time required depending on many factors, the most important being the armies involved. The more troops are involved in a battle, the longer preparations will take. The exception to this is for very one-sided battles. If one side outnumbers the other more than 10:1, additional troops will not make battle preparations take longer. Trying to stop a 1000 men army with 5 soldiers will not delay them very much.

While battle preparations are underway, the battle is visible to others in the area and everyone within action distance can join in, choosing freely which side they wish to support. First Ones joining the battle will also extend the preparation time, but with a decreasing impact (so you cannot delay a battle forever just by having more people join in continuously).

Once you have joined a battle, either by force or by initiating it, you cannot cancel it anymore. You can only avoid fighting a battle by evasive actions.

Unit Setup
----------
Sometimes referred to as your "Battle Setup", your Unit Setup, or Unit Settings, define how your forces deploy on the field, where they deploy, and what they do in the battle. Once things are underway you can't change things. For more information, please view the page on [Units](units).


Battle Resolution
-----------------
Unlike the battles of old, things are a lot less abstract. Gone are the days when you only witnessed the outcome in numbers. Battles can usually be resolved into 5 phases, starting with 1, then looping through phases 2, 3, 4, and 5, in order, until there is only a single surviving side.

1. Deployment Phase (Pre-Battle)
2. Movement Phase
3. Attack Phase
4. Status Phase
5. Rout Phase

Within these phases, all actions are resolved "simultaneously".

### Deployment Phase ###

While it should seem obvious, battles take place somewhere. Where that somewhere is matters quite a bit as it affects a swath of variables that all in turn affect unit abilities. Things like local geography, how far you are from the nearest region, what it's region type is, whether you're assaulting fortifications (or sallying from them), and even the weather, all have a factor in battle.

What also matters is where your unit is in that battle. While we appreciate the many players we have that are devote fans of military sims, Might & Fealty, as a browser game, has to abstract a little in order to keep things enjoyable. The game will take into account your [Unit Settings](units) as it places you, but where it places you in a given line depends greatly on which order it selects your particular unit to be placed.

Yes, your unit has a 2-dimensional position. No, you don't need to know what it is. Yes, the game takes it into account when it decides how your force acts.

### Movement Phase ###

After the initial battle setup, all forces on the field will, in turn, move to what the unit determines to be the *best* spot for it, if it decides to move at all. Melee fighters will, if allowed by unit strategy, advance towards the enemy, while archers in range already will stay where they are and receive a small accuracy bonus.

### Attack Phase ###

After momvenet is completed for all units, all melee units will attack, followed by mixed units, followed by ranged units. Melee fighters will, if in range of another unit, create a battlefield melee, where one or more units in range all slam together in a chaotic mess. Mixed units will do this as well, if they are within a "sword's distance" of another unit. Ranged soldiers will never create a melee. 

Following the creation of a battlefield melee, all units in the melee will have attacks and defends calculated for every soldier depending on who that soldier ends up engaging. Soldiers do not "match-up" in the heat of battle--they attack whoever is convenient and hope to be victorious.

Lastly, ranged fire is calculated. Ranged units will pick a target on the field (either another unit or, if their orders allow, a battlefield melee), determine their accuracy modifier, and then each soldier will fire on that unit, randomly hitting a given soldier within it. In the cases of battlefield melees, yes, this means they can potentiall hit, and kill, allied soldiers.

### Status Phase ###

Once all attacks are caculated, the effects of those attacks are taken into account. This mostly boils down to wounds and deaths, but both of those also affect unit morale, which in turn can cause a unit to break. On a side note, this is also when the game figures out who broke their shield mid-combat, or the effects of your cavalryman losing his mount.

### Rout Phase ###

If a unit morale has sunk far enough in the Status Phase, the unit will flee the field, usualy in a disorganized mess. If, for some reason, they aren't actively engaged in a battlefield melee, the worst is that they may drop some equipment. If they are actively fighting, well, there's a small chance that they'll suffer additional casualties. During this phase it's also calculated whether or not a given soldier actually returns to your service after the retreat.


Morale
------
As already mentioned, morale of soldiers is tracked during battle. Starting morale depends on several factors, the most important ones being:

* The the two sides of the battle are very unequal in size, the weaker side will be afraid and the stronger side more confident. If you are outnumbered several times, the effect is quite considerable.
* The more experience and better equipment a soldier has, the better he will feel about going into battle, and this can offset the above heavily.
* The closer to home (i.e. where he was born and recruited) he fights, the more he will feel the battle to be meaningful. While this affects all soldiers, it most greatly affects militia units, while professionals don't mind nearly as much, and mercenaries will hardly care at all (though they like defending their homes as much as the next guy).

During battle, everything going on around the soldier affects his morale. It goes up when he hits or kills an enemy, it goes down when he is attacked or hit, when his comrades fall around him or when he is outnumbered. If the First One leading the unit falls, either to a disabling wound or a fatal blow, it's quite likely unit morale will shatter. In battlefield melees, killing enemy First Ones will improve morale as much as allied First Ones (outside of that unit) falling will hurt it.

Morale primarily affects the routing chances. Units with low morale will flee the battle sooner than troops with high morale.


Battle Results
--------------
Maybe counter-intuitively, the game engine will not declare a winner for a battle. This is because battles do not happen in a vacuum. If the purpose of the battle was to delay the enemy invasion, then even a defeat can be a victory. If the enemy King died, it might be considered a victory even if you were driven off the battlefield. If you defeated an enemy with horrible losses even though you were numerically superior - is that really a victory?

No, the game leaves it to you to declare yourself victorious.


The game **does**, however, apply battle results for First Ones and individual soldiers on both sides. Your troops can come out of a battle routed, wounded and with lost or damaged equipment, forcing a resupply. They can, of course, also not come out of the battle alive at all.

The same is true for First Ones, except for equipment - First Ones are assumed to have spare equipment and possibilities to replace killed horses, etc. But nobles can be victorious, defeated, wounded, killed or captured (see [prison]). They are considered victorious if their side is in control of the battlefield at the end of the battle, and defeated if all troops on their side were wiped out.
As a result of battles over settlements, victorious attackers will enter a settlement. Defeated troops that had previously forced their way into the settlement (i.e. who do not have permission to enter) will be forced outside.

Wounds on both soldiers and First Ones do not affect their actions in the game, but additional wounds make death more likely. Going into battle while still wounded can be very dangerous.


After-Battle Actions
--------------------
If anyone involved in battle had travel set, he will move a short distance (about 25% of daily travel) immediately after the battle. This represents retreat from the battlefield, orderly or not, and is also intended to prevent players from keeping their enemies stationary by enaging them in multiple small battles.

Anyone in a battle will also need to regroup afterwards, during which time he cannot join or initiate new battles. The time needed to regroup depends on the number of soldiers under his command and will in most cases amount to somewhere between 30 minutes and 2 hours (real time).
