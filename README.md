Roundcube Webmail GlobalAddressbook
===================================
This plugin adds an SQL based global address book to Roundcube. It can be
global per installation, per IMAP host or per domain.

ATTENTION
---------
This is just a snapshot from the GIT repository and is **NOT A STABLE version
of GlobalAddressbook**. It is Intended for use with the **GIT-master** version
of Roundcube and it may not be compatible with older versions. Stable versions
of GlobalAddressbook are available from the
[Roundcube plugin repository][rcplugrepo] (for 1.0 and above) or the
[releases section][releases] of the GitHub repository.

License
-------
This plugin is released under the [GNU General Public License Version 3+][gpl].

Even if skins might contain some programming work, they are not considered
as a linked part of the plugin and therefore skins DO NOT fall under the
provisions of the GPL license. See the README file located in the core skins
folder for details on the skin license.

Install
-------
* Place this plugin folder into plugins directory of Roundcube
* Add globaladdressbook to $config['plugins'] in your Roundcube config

**NB:** When downloading the plugin from GitHub you will need to create a
directory called globaladdressbook and place the files in there, ignoring the
root directory in the downloaded archive.

Config
------
The default config file is plugins/globaladdressbook/config.inc.php.dist
Rename this to plugins/globaladdressbook/config.inc.php

**'username'**

This is the name of the dummy user which holds the global address book.
The username does not have to belong to a valid email account. The username
will be stored in the Roundcube database but will not be able to log into
Roundcube unless it belongs to a valid email account on your server.

To create a single global address book for everyone who access Roundcube set
this options to something like: '[global_addressbook_user]'

To create a global address book per email domain which Roundcube serves set
this options to something like: 'global_addressbook@%d'

The username can contain the following macros that will be expanded as
follows:
* %d is replaced with the domain part of the logged in user's username
* %h is replaced with the imap host (from the session info)

**'perms'**

Restrict the actions that can be performed by users in the global address book
* 0 - global address book is read only
* 1 - users can add, edit and delete contacts (full permissions)
* 2 - users can add but not edit or delete contacts
* 3 - users can add and edit but not delete contacts

**NB:** The globaladdressbook_readonly option was deprecated in version 1.9,
replaced by globaladdressbook_perms.

**'force_copy'**

Always copy a contact from the global address book to another one, for example
when using drag 'n drop. Default behaviour is to move the contact.

**'groups'**

Should contact groups be available in the global address book

**'admin'**

The admin is a user or users who will always have full read/write access even
if the address book is set to read only. The follow options are available:
* To set a single user as admin then enter their username as a string like:
  'admin@domain.com'
* If you wish give admin rights to multiple users then enter the usernames in
  an array like: array('admin1@domain.com', 'admin2@domain.com')
* You can also use regual expressions to match the admin username, regular
  expressions must be started and finished the a '/'. Eg: '/^admin@/'

**'autocomplete'**

Show contacts from this book in the auto complete menu when composing an email

**'check_safe'**

Use addresses in the global address book to identify known senders before
displaying remote inline images in HTML messages (in addition to other
configured address books).

[rcplugrepo]: http://plugins.roundcube.net/packages/johndoh/globaladdressbook
[releases]: http://github.com/JohnDoh/Roundcube-Plugin-Global-Address-Book/releases
[gpl]: http://www.gnu.org/licenses/gpl.html