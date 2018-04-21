Messaging Bundle
================

This is a bundle for in-app messaging services using Symfony2 and (at the moment) Doctrine 2.

Dependencies
------------
PHP Markdown. Add this to composer.json: 

"knplabs/knp-markdown-bundle": "1.2.*@dev",

Note that this relies upon dflydev's markdown package, which is deprecated. It should really use "michelf/php-markdown": "1.4.*@dev" as a dependency.


Features
--------
* Personal messages and forum-like broadcasts in the same system
* Threaded messages, grouped into conversations and topics
* Replies and split-replies (reply and start a new topic)
* Two-way references between messages
* Flags, tags and scores
* Permission system
* time-based access controls (join a conversation and see all posts or only those posted after you join, leave a conversation and you retain access to the old messages you could see while you were part of it)
* markdown support


Vision
------
Basically, this is messaging as I believe it should be. The main purpose is that it should be:

* intuitive
* powerful
* personal

To be **intuitive**, the system uses natural clusters to group elements. People cluster into groups naturally, and messages cluster into conversations naturally. Messages can be replies to other messages, or new contributions to the topic, independent of others. Conversations can contain sub-conversations, when people go off on a tangent. A message cannot only have multiple replies, it can also be a reply to multiple messages - if three people say essentially the same thing, you can write one reply that addresses them all, and will show up as a "reply to my messages" for all of them.

Messages are being used differently by different people, and often messages are not done with once read. To be both **powerful** and **personal**, this messaging system supports flags, tags and permissions:

### Flags ###
A set of pre-determined flags can be toggled on messages, allowing for an extremely quick and easy way to mark messages for several purposes. By default, there are flags for:

* important - a general-purpose flag for whatever the user thinks is important, mostly used as a filter ("show me all important messages")
* act - a message requires further action, very useful if you are reading your messages to mark those you need to return to later because they require some kind of action (e.g. a reply, but also real-world activities)
* remind - a flag to put a message into a reminder queue that will pop up after a set time or something (details TBD)
* keep - to make sure a message isn't removed by cleanup or maintenance but is kept around for reference

There is also a non-binary flag: Score. A score is simply an integer number (can be negative) that you can assign to a message. This allows for an alternative, more fine-grained importance rating (e.g. "show me messages with a score of 3 or more").

### Tags ###
For keyword search or any other purpose the user can think of, he can add tags in the form of words to messages. This can be used in infinite ways, for example by tagging messages by topic.

### Permissions ###
Conversations are supported by a permission system, where the owner/creator of a conversation (who automatically has all permissions) can assign permissions to other participants. The default set of permissions are:

* owner - can do anything, and is protected from other people editing or removing him (this is a permission so a conversation can have multiple owners)
* participants
  * add - add participants to this conversation
  * remove - remove participants from this conversation
  * edit - change the permissions of participants
* write - allowed to write messages in this conversation (this can be used to create read-only conversations, or conversations where only some participants can write but everyone can read)


Documentation
-------------
Installation and usage instructions can be found in the [manual](Manual.md).


Current Status
--------------
This is currently somewhat closely coupled to the game it is being made for, [Might & Fealty](http://mightandfealty.com/). However, I am trying to
isolate those dependencies and it very much is intended to be a general-purpose tool. Any help moving it into that direction would be very much
appreciated.

