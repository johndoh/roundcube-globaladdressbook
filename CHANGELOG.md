# Roundcube Webmail GlobalAddressbook

## Version 2.1 (2022-06-18, rc-1.5)

- Drop support for PHP < 7.0
- Improve PHP8 compatibility

## Version 2.0.2 (2021-11-12, rc-1.3)

- Fix plugin installation via Composer on PHP8+

## Version 2.0.1 (2021-03-26, rc-1.3)

- Restore support for localized address book names (#54)

## Version 2.0 (2021-01-03, rc-1.3)

- Complete rewrite of config options
- Support multiple address books on single host (#52)
- Add `globaladdressbook_permissions` plugin hook
- Remove regex in `admin` config var, use new hook instead
- Define address book name in config, remove localizations
- Change default username to `global_addressbook_user`

## Version 1.12 (2020-11-20, rc-1.3)

- Add `globaladdressbook_allowed_hosts` config option
- Remove support for depreciated config option `globaladdressbook_readonly`

## Version 1.11.1 (2019-02-26, rc-1.0)

- Fix bug in handling of `globaladdressbook_admin` regex (#47)

## Version 1.11 (2018-03-11, rc-1.0)

- Include plugin in all tasks
- Better support when using regex for admin ids

## Version 1.10 (2017-05-19, rc-1.0)

- Add `%i` macro to get domain from default identity

## Version 1.9 (2014-08-31, rc-1.0)

- Replace `globaladdressbook_readonly` option with `globaladdressbook_perms`
- Add option to disable `move` operation for contacts

## Version 1.8 (2013-12-01, rc-1.0)

- Call `email2user` conversion for usernames with @ in (incase of username post processing)
- Add support for new `message_check_safe` hook
- Update config file var names to match core

## Version 1.7 (2013-05-19, rc-1.0)

_code branching/tagging no longer sync'd to roundcube versions_

## Version 1.6 (2013-03-03, rc-0.9)

- Update for Roundcube framework

## Version 1.5 (2012-04-14, rc-0.8)

- Update after r5781

## Version 1.4 (2010-08-01, rc-0.4)

- Update hooks (r3840)

## Version 1.3 (2010-08-01, rc-0.4)

- Username parsing now in core (r3774)
- Enable groups support (r3614)
- Prepare for groups stuff

## Version 1.2 (2010-02-07, rc-0.4)

- Update after r3258
- Add `admin` user option
- Localise address book name
- Make config simplier (may cause problems in existing installations, ensure user host in db is set to localhost)
- Fix buggy user creation
- Allow for per domain address books
- Allow for per host address books