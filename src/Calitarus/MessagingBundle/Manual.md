Manual
======

(TODO, sorry)



Replacements Needed
-------------------

### app user ###
In User.orm.xml the field app_user references the user object in your application and needs to be changed from what I use.
The only requirement for this entity is that it must have a getName() method (if it has a name field, that'll be auto-generated).


### link() ###
In the Twig templates, there is a reference to a function link(), usually in a form like link(msg.sender.appuser).
This function creates a hyperlink to the user details page in the application. You can replace it with your own or remove it and use something like msg.sender.name instead of the function call. If you remove it, you can also remove the |raw after the trans() because it is only required because the link() function generates a hyperlink


### contacts ###
In Form/NewConversationType.php you will want to replace the block with the comment "// Might & Fealty: contact nearby characters", because as it says, it
is specific to my game. Instead, you want to replace it with something different that allows people to contact each other. The way the system is set up right
now you can contact anyone that is nearby (as in that block) or in any of your existing conversations.

The easiest way that is probably appropriate for most applications is to simply replace both blocks with an entity of all users, that way everyone can contact everyone else.
