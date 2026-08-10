# Shared User and Profile Persistence Refactor

Status: Proposed

Prepared: 10 August 2026

## Purpose

Create one reliable service for creating and updating Joomla users, RA profiles,
and user-group mappings. The service will live in `com_ra_tools` and will be used
by `com_ra_setup` first, then by `com_ra_mailman` where appropriate.

This document is intended to be sufficient context for a future development
session. It covers a cross-repository refactor; it is not an instruction to
implement all changes in one commit or release.

## Immediate defect versus refactor

The immediate Step 6 defect in `com_ra_setup` must remain a small, independent
change:

- `SixModel::saveProfile()` must not accept an email argument.
- Neither the insert nor update of `#__ra_profiles` may reference an `email`
  column.
- Email remains on `#__users` and the profile is linked by
  `#__ra_profiles.id = #__users.id`.

Apply and verify that correction before starting this refactor. Do not make the
production defect depend on completion of the cross-component work below.

## Repositories and current code

### RA Setup

Repository: `ra-setup`

Primary file:

- `com_ra_setup/site/src/Model/SixModel.php`

Relevant methods:

- `saveUser()` finds a user by case-insensitive email, updates an existing
  user's name, or inserts a new Joomla user.
- `saveProfile()` inserts or updates an RA profile.
- `addUserToGroup()` resolves a Joomla group by title and creates the mapping
  if absent.
- `persistPeople()` coordinates users, profiles, contacts, permissions, and
  optional Mailman access inside a transaction started by `SixController`.

RA Setup requires RA Tools. At the time this plan was written,
`com_ra_setup/script.php` requires RA Tools version `4.0.9` or later.
Mailman is optional, so Setup must not depend on Mailman's helper classes.

### RA Mailman

Repository: `ra-mailman`

Primary file:

- `com_ra_mailman/site/src/Helpers/UserHelper.php`

The helper contains useful user/profile concepts but is not suitable for direct
reuse by Setup:

- Its constructor creates Mailman-specific collaborators and reads Mailman
  configuration.
- Its API is based on mutable public properties such as `name`, `email`,
  `group_code`, and `user_id`.
- `createUserDirect()` inserts rather than upserting and then looks the user up
  by email instead of trusting the insert ID.
- It uses a shared hard-coded password hash.
- Group assignment uses numeric IDs.
- `createProfile()` uses `$db` in its update branch before `$db` is initialised.
- Mailman-specific imports, notifications, subscriptions, reports, and purge
  operations are mixed into the same class.

Mailman's profile creation does correctly avoid writing email to the profile and
links the profile to the Joomla user by `id`.

### RA Tools

Repository: `ra-tools`

Primary existing file:

- `com_ra_tools/site/src/Helpers/UserHelper.php`

This is an older parallel implementation of user/profile creation. It confirms
that persistence is a shared concern, but it also uses mutable state, direct SQL,
numeric group IDs, and unrelated notification/purge behaviour. Do not extend it
with another conditional code path. Introduce a focused service and migrate
callers deliberately.

## Data ownership and invariants

The shared implementation must enforce these rules:

1. `#__users.email` is the authoritative email address.
2. Shared persistence must never require or write `#__ra_profiles.email`.
3. `#__ra_profiles.id` contains the related `#__users.id`.
4. Email comparisons used for identity matching are case-insensitive and inputs
   are trimmed and normalised before persistence.
5. Existing users and profiles are updated idempotently; rerunning an operation
   must not create duplicates.
6. Joomla user groups are resolved by title, not assumed numeric IDs.
7. Existing transactions belong to the calling workflow. The service must not
   commit or roll back a transaction started by a caller.
8. Database values must be quoted through Joomla's database API. Do not build
   SQL with unescaped user input.
9. The service must work whether `member_id` is auto-incremented or must be
   supplied. Schema handling must be documented and tested.
10. Mailman must remain optional for RA Setup.

## Proposed design

Add a small service under RA Tools, using the repository's current Joomla
namespace and autoloading conventions. Choose the final class name after
checking existing naming conventions; `UserProfileService` is the working name.

Suggested location:

- `com_ra_tools/site/src/Service/UserProfileService.php`

The service should receive its dependencies explicitly where practical:

- `DatabaseInterface`
- the current actor ID, or a small callable/provider for it
- a clock/date provider only if needed for deterministic unit tests

Avoid public mutable state. Prefer typed arguments, typed return values, and
exceptions that preserve the cause of a persistence failure.

Suggested public operations:

```php
public function findUserByEmail(string $email): ?object;

public function saveUser(string $name, string $email, UserSaveOptions $options): int;

public function saveProfile(
    int $userId,
    string $preferredName,
    string $homeGroup
): void;

public function addUserToGroup(int $userId, string $groupTitle): bool;
```

`UserSaveOptions` is optional. A simple set of typed scalar arguments is
acceptable if only a few behaviours need configuration. The API must make these
behaviours explicit:

- whether a newly created account is blocked;
- whether password reset is required;
- whether an existing user's name is updated;
- whether username should be the normalised email for new users.

Do not include Mailman subscriptions, contacts, notification emails, import
reporting, or component-specific role rules in this service.

## Identity and email-change decision

Before implementation, decide how a caller identifies an existing person whose
email has changed.

The present Step 6 form submits names and email addresses but no Joomla user ID.
Because `SixModel::saveUser()` identifies existing users by submitted email, an
email change can create a second user rather than update the previously linked
user.

Recommended resolution:

- Include the known Joomla user ID as trusted server-side state when Step 6
  loads an existing committee contact.
- On save, validate that the submitted association is one the wizard previously
  loaded; do not trust an arbitrary hidden user ID by itself.
- If a known user ID is present, update that user's name/email after checking
  that the new email and username are not owned by another user.
- If no known ID is present, find by normalised email or create a new user.

Document this behaviour in the service contract and cover it with tests. If this
decision is deferred, the initial service must preserve current behaviour and
the limitation must remain explicitly documented.

## Phased implementation

### Phase 0: Stabilise Step 6

1. Remove email from `SixModel::saveProfile()` and its call site.
2. Verify profile inserts and updates against a schema without profile email.
3. Commit this independently from the shared-service refactor.

### Phase 1: Characterisation and schema checks

1. Inventory all callers of both existing `UserHelper` classes.
2. Record required differences in account blocking, reset, notifications, and
   group membership.
3. Confirm supported `#__ra_profiles` variants, especially `member_id` null,
   primary-key, and auto-increment behaviour.
4. Add characterisation tests around behaviour that must be preserved.
5. Confirm the minimum supported Joomla and PHP versions before using newer
   language or framework features.

### Phase 2: Build the RA Tools service

1. Add the focused service and dependency wiring to `com_ra_tools`.
2. Implement normalised email lookup and collision checks.
3. Implement Joomla user insert/update without a shared hard-coded credential.
   Prefer Joomla's user APIs if their multi-user behaviour is verified; otherwise
   use the database API with a cryptographically random password hash.
4. Implement profile insert/update without any profile email dependency.
5. Implement idempotent group-title mapping.
6. Add unit/integration tests for the service.
7. Release RA Tools with an incremented component version.

### Phase 3: Migrate RA Setup

1. Raise RA Setup's minimum RA Tools version to the version containing the new
   service.
2. Replace `SixModel::saveUser()`, `saveProfile()`, and `addUserToGroup()` with
   calls to the service.
3. Keep contact creation, committee role decisions, and optional Mailman access
   in `SixModel`.
4. Preserve the transaction controlled by `SixController`.
5. Test fresh setup, reruns, existing users, email collisions, and optional
   component combinations.

### Phase 4: Migrate RA Mailman

1. Make Mailman's user/profile creation delegate to the RA Tools service.
2. Keep Mailman-specific subscriptions, notifications, import processing,
   reporting, and lapsed-user handling in Mailman.
3. Preserve public compatibility temporarily if existing controllers still set
   `UserHelper` properties. Mark compatibility wrappers as deprecated.
4. Fix or remove superseded profile variants such as `createProfile_1()` and
   `createProfile_3()` only after confirming there are no callers.
5. Test front-end registration, administrator creation, and data imports.

### Phase 5: Consolidate and document

1. Migrate remaining RA Tools callers to the focused service.
2. Remove duplicated persistence code only after repository-wide caller checks.
3. Document authoritative data ownership and supported schema variants.
4. Update component versions and release notes in dependency order:
   RA Tools, RA Setup, then RA Mailman.

## Required tests

At minimum, cover:

- New user creation stores email only in `#__users`.
- Existing user lookup is case-insensitive.
- Existing user name update does not create a duplicate.
- Username/email collision with another user is rejected.
- Profile insert uses the Joomla user ID as `ra_profiles.id`.
- Profile update changes only profile-owned fields and audit columns.
- Profile persistence works when no `email` column exists.
- Supported `member_id` schema variants work correctly.
- Adding the same group twice remains idempotent.
- A missing group title produces the agreed result without a partial hidden
  failure.
- Caller-owned transaction rollback removes all user/profile/group changes when
  a later workflow step fails.
- Step 6 works with Mailman enabled and disabled.
- Rerunning Step 6 does not create duplicate users, profiles, contacts, or group
  mappings.
- If user-ID-based email changes are implemented, both successful changes and
  collision rejection are covered.

## Acceptance criteria

The refactor is complete when:

- RA Tools contains one tested, component-neutral service for user, profile, and
  group persistence.
- RA Setup uses that service and contains no duplicate persistence methods.
- RA Mailman delegates shared persistence while retaining Mailman-only logic.
- No shared code writes or depends on `ra_profiles.email`.
- All supported profile schema variants pass tests.
- Setup remains functional without Mailman installed or enabled.
- Account creation behaviour, password/reset policy, and identity matching are
  explicit rather than implicit in helper state.
- Minimum dependency versions and release order are updated and documented.

## Out of scope

- Redesigning Joomla Contacts.
- Reworking committee role policy or Mailman subscription policy.
- Migrating historical profile email data.
- Removing legacy helpers before all callers have migrated.
- General cleanup of Mailman import, purge, or reporting code unrelated to
  persistence delegation.

## Starting checklist for the next session

1. Read this document in full.
2. Inspect uncommitted changes in every repository and preserve unrelated work.
3. Confirm that the Phase 0 SixModel correction has been committed.
4. Re-run caller searches; repository code may have changed since this plan was
   prepared.
5. Confirm current RA Tools, RA Setup, Mailman, Joomla, and PHP versions.
6. Resolve the identity/email-change decision before finalising the service API.
7. Implement and release one phase at a time, with tests before migrating the
   next component.
