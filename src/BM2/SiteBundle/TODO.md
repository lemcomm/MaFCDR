### Dungeons ###
* check if dungeon closing finally works - not really, but somewhat, very strange???

### Fixes ###
* session data update doesn't seem to work - why?

### Important Upcoming Changes ###
* inactive characters should leave conversations after some long time (90 days or so?)

* enforceable claims:
** take possible from outside
** grant estate with enforceable claim ("in fief")

* bandit characters:
** not count against character limit
** respawn upon death
** "pool" of inactives that people can take one from if available and they want - show availability somewhere on character page?
** limitations (this can work with the trial accounts limits maybe?)

* finish inn and mercenary code
* re-balance some building restrictions to make high-end stuff (swords, chain mail, etc.) limited to cities
* destruct button for buildings and features resulting in negative construction (how to handle on database? maybe add a field showing status (construction )
* dead rulers need to free up position in all cases http://bugs.battlemaster.org/view.php?id=8254 - this can happen if the dead inherits a position (which should not be possible in the first place) -- cleanup?
* finish region familiarity
* effect of captives (and their troops!) on your travel time, etc.
* option for captor to disband (or slaughter) troops/entourage of the captive (to shed excess weight for travel)
* assigning soldiers to people in battle should change the battle preparation time
* payment system completion (BitPay)
* [Amazon Payment](https://payments.amazon.com/developer)
* complete auto-login system
* effect of roads on travel speed
* road maintenance and deterioration
* resupply from camp followers (database entry exists, need to fill it up and then use it)
* Show a small section of the PAST travel for spotted characters (aka trail), allow people to guess direction of future travel but with the uncertainty of people going zig-zag or misdirection...
* realm rulers should be able to control conversations - or at least permissions (deny people write permission, etc.)
* new/completed settlement permission system
** the above would obsolete: lists public doesn't yet work fully: http://forum.mightandfealty.com/index.php/topic,2307.msg12357.html#msg12357
* fix icons locations, I broke them I think
* named character groups instead of primary flag
* fix interaction from inside to outside of settlements

### Other Upcoming Changes ###
* dungeons: ability to select 2 actions (next and the one after that)
* realm positions permissions completion
* [configurable welcome message for new knights](http://forum.mightandfealty.com/index.php/topic,2319.msg12934.html#msg12934)
* more refined inheritance system (return to liege, etc.) based upon realm laws
* burning bridges and other features, as well as maintenance for bridges, watchtowers and docks
* killers rights (stubs exist) -- and decide what triggers them (e.g. suicide as prisoner, executing prisoners? death in combat? duel?)
* surrender yourself outside battle (become a prisoner)
* switching more actions to in-turn / time-based resolution
* thralls/slaves and the ability to enthrall enemy populations during looting [1](http://forum.mightandfealty.com/index.php/topic,2235.0.html), [2](http://forum.mightandfealty.com/index.php/topic,2235.msg11671.html#msg11671)
* Army attrition - what really destroys armies is not combat, but wear and tear and the game needs to simulate that (both equipment and people). Not to the extent of real life (where losses to attrition often overshadow combat losses), but enough to make logistics matter.
* implement looting (which will drop economic security, among other things)
* block vision into settlements (map and "nearby nobles")
* river widths and tributaries that don't block travel
* context-relevant manual links
* for time-based actions, the success screen should also show the completion time.
* move pages directory into translations so it goes to the translations github ?


### Combat ###
* most of the fields in BattleParticipant don't seem to be used?
* possible exploit: Have one on the inside initiate battle and others from outside join in?


### Construction ###
* [village specialisation](http://forum.mightandfealty.com/index.php/topic,2435.0.html)


### Economy ###
* replace the unrealistic "minimum population" with "minimum sustainable" values, maybe 2 of them, below which a building is highly unproductive or something - allow people to build it, but make it be almost worthless. -- this might not work for some buildings such as fortifications!
* shouldn't be able to build mines in regions without metal
* show more details for buildings (after a "show details" button or such), no reason to hide the precise numbers, is there? Or maybe there is? But at least give people some idea to the operations values, which are currently totally hidden


### Cleanups ###
* do we need has_weapon, has_armour and has_equipment anymore or can we use old_weapon, etc. instead ?
 ==> depends... with the current system only retraining changes it, so we can lose our new weapon and still have a record of the last one we trained


### Messaging etc. ###
* sort conversation overview (tablesorter - especially by date of last activity!)
* better markdown editor with splitscreen preview: https://github.com/patbenatar/crevasse
* idea: information exchange - putting location-based notes on the map that are shared with lists(?) or realm (e.g. scout reports, battle reports, etc.)
  or a more general concept: Allow attaching notes to any character, settlement, realm - owned by creator, but shareable
* timeout/cleanup ideas:
** limited timespans code for conversations, i.e. when you join you don't get access to the entire past and when you leave you don't instantly lose access to everything
** time-limit conversations - set the m to "inactive" after nothing got posted to them for x days (14? 30?) and then delete them after another x days.
** do something about inactive participants, maybe remove them after x days, or when they go inactive
** later on maybe remove participants from any conversation they didn't star/mark at least one message in after x days
* message system: tags
* message system: complete flags (flag-specific actions)


### Politics ###
* "going rogue" text needs update - when you hold land, you don't really become a rogue!
* no message to realm if it gains settlements through an oath of fealty!
* additional view that shows hierarchy with liege + ruler names instead of realms - http://forum.mightandfealty.com/index.php/topic,2637.0.html
* realm event if diplomacy changes!
** maybe also to other interested parties (nearby realms?)


### User Interface ###
* characters screen sucks on smaller screens (announcements section pushes it down)
confusing interface here:
http://forum.mightandfealty.com/index.php/topic,1325
http://forum.mightandfealty.com/index.php/topic,2251.0.html






http://forum.mightandfealty.com/index.php/topic,1181
http://forum.mightandfealty.com/index.php/topic,1182.0.html (heraldry designer)
http://forum.mightandfealty.com/index.php/topic,1352 (combat bug - archer not firing)


http://forum.mightandfealty.com/index.php/topic,2114 (important part: recently deceased characters can have children - we don't want that, but we do want ancestors, so find a way to do both)

Right now, reclaim can be abused for teleporting troops. Need to fix that somehow... - maybe take distance into account for the reclaim chance?



There is no way for a lord to disown a vassal!
http://forum.mightandfealty.com/index.php/topic,1114 (cannot remove vassals)


event log issues:
http://forum.mightandfealty.com/index.php/topic,1122
http://forum.mightandfealty.com/index.php/topic,1049
http://forum.mightandfealty.com/index.php/topic,1004
http://forum.mightandfealty.com/index.php/topic,411
http://forum.mightandfealty.com/index.php/topic,1306
http://forum.mightandfealty.com/index.php/topic,1308


http://forum.mightandfealty.com/index.php/topic,1098 (rss feed)
http://forum.mightandfealty.com/index.php/topic,1092 (inheritence bug?)


loss to same realm gives a message? - maybe it's a subrealm thing? -- this is marked as TODO in the code, yes
http://forum.mightandfealty.com/index.php/topic,1183.msg7801.html#msg7801


character family view betrays if same account or other - might want to remove that (but since we use the same template, that'd require adding an if condition or something).


old travel bug, still not solved, apparently:
http://forum.mightandfealty.com/index.php/topic,523


excellent idea (2nd one):
http://forum.mightandfealty.com/index.php/topic,1333.msg9229.html#msg9229
- basically, "tag" things with conditions - I wanted to do something like that for the actions anyway!
- not sure if it should be automated actions or triggers for notifications...
- see Condition.orm.xml


New takeover action:
Interrupted or at least delayed if you get attacked.


construction not stopping during starvation?
http://forum.mightandfealty.com/index.php/topic,1294.msg9404.html#msg9404



examples of names (and later images) for the culture packs:
http://forum.mightandfealty.com/index.php/topic,2218.msg11468.html#msg11468



distance on route planning/setting is still in metres:
http://forum.mightandfealty.com/index.php/topic,2308


buttonlist (e.g. used on relations) still hides unavailable options - which makes it impossible to know where the "swear oath" option is if it's not available... ugly...

oath: it should be possible to swear to the owner of a settlement you are in...


proposal: estimate travel times:
http://forum.mightandfealty.com/index.php/topic,2395.0.html


There should be an option to avoid battles IF ALL INVOLVED AGREE - some kind of "dont fight" toggle that cancels the battle only if everyone in it has it set:
http://forum.mightandfealty.com/index.php/topic,2405



dead prisoners - http://forum.mightandfealty.com/index.php/topic,2498.msg14511.html#msg14511


missing .js, .css, etc. files should trigger a 404 and not a 500 (seems that Symfony gets launched and looks for a path or something)


name-able character groups:
http://forum.mightandfealty.com/index.php/topic,2682.0.html


