# Tornado Engine Access-Google

A [Tornado Engine](https://github.com/DylanBanta-WM/TornadoEngineCore) module: gives roles to people by Google user, Google Group or organizational unit.

- Rules are set under Users and roles → Google. Each gives one role to a Google user, a group's members, or the people in an OU.
- A nightly job (Cron) adds the people who match, so they can sign in, and gives them the role. People who leave the group or OU, or are suspended, lose it. Roles given by hand are left alone.
- If GAM fails, nothing changes.

Needs the Access, GAM and Cron modules.
