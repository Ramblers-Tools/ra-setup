# SMTP2GO Step 7 provisioning implementation plan

Status: Proposed, aligned with the README email-configuration requirements

Prepared: 31 August 2026

Revised: 5 September 2026

## Objective

Step 7 collects and validates the proposed SMTP2GO sub-account name and the
Joomla user who is to receive delivery-exception notifications. After the user
gives final confirmation in Step 8, create the SMTP2GO sub-account, create its
restricted API key, register the site's hostname as a sender domain, and update
the configured `#__ra_api_sites` record to use the generated key.

This document is a design and implementation plan only. No SMTP2GO calls or
provisioning changes have yet been implemented.

## Provisioning context

RA Setup has never been released, so no migration or backward-compatibility
work is required for its fields or configuration semantics.

The wizard runs on a new website cloned from a private template. Each clone
initially contains a local copy of the SMTP2GO master credential. The template
retains its own master credential, so the clone's copy may safely be overwritten
with its generated sub-account key. A catastrophic failure can be recovered by
cloning the site again and repeating the complete configuration.

Every wizard operation is also available in the Joomla back end to a
knowledgeable administrator. Those facilities provide an exceptional recovery
route, but the wizard is intended to avoid manual intervention wherever
possible.

## Existing implementation

### Step 7

The current Step 7 flow is implemented in:

- `com_ra_setup/site/forms/seven.xml`
- `com_ra_setup/site/src/Model/SevenModel.php`
- `com_ra_setup/site/src/Controller/SevenController.php`

The form collects:

- `domain`, currently labelled "Email domain", limited to 12 characters and
  initially derived from the first 12 characters of the header website title;
- `contact_id`, the Joomla user to notify about delivery exceptions.

Before release, rename the `domain` field to `sub_account` and label it "Sub
account". A sub-account name and the website/sender hostname are different
values. Step 1 already stores the website URL for the current cloned-site
session in `com_ra_tools.website`; changing that hostname after provisioning is
not supported.

The controller currently starts a database transaction and updates only these
`com_ra_delivery` parameters:

- `subdomain = domain`
- `contact_id = contact_id`

This differs from the README requirement in two respects:

- the proposed sub-account name must be checked remotely in Step 7 before it
  is accepted; and
- the selected `contact_id` value must be stored as `notify_user`.

The second requirement also conflicts with the current RA Delivery
configuration, which defines `notify_user` as an email address and uses it
directly as a message recipient. Because RA Setup has not been released, no
migration is required: implement `notify_user` as a Joomla user ID and resolve
the user's current email address at send time.

Step 7 must not create remote resources. Provisioning begins only when Step 8
is confirmed.

Step 7 is deliberately repeatable. It may be rerun before provisioning to
correct the proposed sub-account name, select a different notification user,
or reflect a changed Webmaster. Its SMTP2GO availability search is read-only,
and Step 7 must neither create nor clear the `#__ra_control` guard. If Step 7 is
made available administratively after the guard exists, saving its local values
must not make Step 8 available again. The irreversible check belongs to Step 8.

### SMTP2GO integration in RA Delivery

`com_ra_delivery/site/src/Helper/SmtpHelper.php` uses
`Smtp2goActivityService` for activity polling and message sending.
`Smtp2goActivityService`:

- loads a record from `#__ra_api_sites` using the configured
  `com_ra_delivery.smtp2go_api_site_id`;
- uses the record's `url` as the API base URL;
- uses the record's `token` as the `X-Smtp2go-Api-Key` header;
- calls `/v3/activity/search` and `/v3/email/send`;
- parses SMTP2GO errors from `data.error`, `data.error_code`, and `request_id`.

The existing service provides useful examples, but its private transport method
cannot be reused directly. New code should not copy its direct cURL and
`Factory::getDbo()` implementation. A shared, injectable SMTP2GO client should
be introduced instead.

### `#__ra_api_sites`

RA Tools currently defines the relevant columns as:

| Column | Required SMTP2GO value after provisioning |
|---|---|
| `sub_system` | `RA Delivery` |
| `title` | `Sub account name = <name>; sub account id = <SMTP2GO ID>` |
| `url` | Preserve the endpoint from the master record |
| `token` | The unmasked API key returned by `/v3/api_keys/add` |
| Other fields | Preserve their existing values |

The `token` column is `VARCHAR(255)`, which is sufficient for the currently
observed SMTP2GO key format. The table does not currently have a field for an
SMTP2GO sub-account ID. The README requires the master record selected by RA
Delivery to be updated in place after all four provider operations succeed;
it does not require a second API-site row.

## Confirmed SMTP2GO API contract

All calls use JSON over HTTPS and authenticate with a master API key in the
`X-Smtp2go-Api-Key` header. SMTP2GO also supports passing the key in the body,
but the header should be the single authentication mechanism used here.

### Create the sub-account

Call:

```text
POST https://api.smtp2go.com/v3/subaccount/add
```

The current API requires `fullname`. It also accepts an optional
`subaccount_email`, a billing-cycle `limit`, and optional paid or security
features. Use the positive integer `email_limit` from the RA Setup component
configuration. The endpoint is limited to 50 calls per hour.

Initial payload, subject to the decisions below:

```json
{
  "fullname": "<validated Step 7 sub-account name>",
  "subaccount_email": "<current Webmaster email>",
  "limit": "<configured com_ra_setup email_limit>",
  "dedicated_ip": false,
  "archiving": false,
  "enforce_2fa": false,
  "enable_sms": false
}
```

Official reference: [Add a subaccount](https://developers.smtp2go.com/reference/add-subaccount).

The Webmaster is identified by user ID in Steps 5 and 6. Resolve that user's
current email immediately before provisioning and use it as
`subaccount_email`. Dedicated IP, archiving, 2FA enforcement, and SMS are
confirmed disabled.

### Find an existing sub-account

Step 7 calls:

```text
POST https://api.smtp2go.com/v3/subaccounts/search
```

Use `fuzzy_search: false`, an exact `search_terms` value, and an appropriate
state filter. Only zero matches permits Step 7 to continue. Any existing exact
match must log and display:

```text
Unable to register sub-account <sub-account> as it already exists
```

Ambiguous or inexact results must also stop processing with a sanitised error.
An existing remote sub-account is not automatically reused.

Official reference: [View subaccounts](https://developers.smtp2go.com/reference/search-subaccounts).

### Create the sub-account API key

Call:

```text
POST https://api.smtp2go.com/v3/api_keys/add
```

Pass the sub-account's unique ID as `subaccount_id`. Give the key a stable
description and explicitly specify its status. The required least-privilege
permissions are `/email/send` and `/activity/search`; no administration
permissions are to be granted. The endpoint is limited to five calls per
minute.

Payload:

```json
{
  "description": "RA Delivery - <sub-account name>",
  "status": "allowed",
  "endpoints": [
    "/email/send",
    "/activity/search"
  ],
  "subaccount_id": "<SMTP2GO sub-account ID>"
}
```

The requested endpoints must be a subset of the permissions held by the master
key making the call. Official reference:
[Add a new API key](https://developers.smtp2go.com/reference/add-api-key).

The June 2026 SMTP2GO changelog says that `api_keys/view` and `api_keys/edit`
now return masked key values. It is therefore an important design inference
that the unmasked value returned by `api_keys/add` must be validated and stored
immediately; it cannot safely be recovered later through the view endpoint.
See the [SMTP2GO API changelog](https://developers.smtp2go.com/reference/changelog).

### Register the sender domain

After the sub-account and API key have been created, use the master credential
to call:

```text
POST https://api.smtp2go.com/v3/domain/add
```

Parse and validate `com_ra_tools.website` as a URL and pass only its canonical
DNS hostname; do not pass a scheme, path, query string, or fragment. Associate
the domain with the newly created account using `subaccount_id`:

```json
{
  "domain": "<canonical website hostname>",
  "subaccount_id": "<SMTP2GO sub-account ID>",
  "auto_verify": false
}
```

This is the fourth SMTP2GO operation required by the README. A successful
response contains a `data.domains` array with domain details and DNS setup
values. Registration is not the same as verification: required DNS records
must be published externally before a later verification can succeed.

Official reference: [Add a sender domain](https://developers.smtp2go.com/reference/add-sender-domain).

### Activity filtering consequence

SMTP2GO documents the `activity/search.subaccounts` filter as an array of
sub-account IDs. The existing RA Delivery code passes the configured
`subdomain` text instead. Provisioning must not assume that the Step 7 domain is
a valid activity sub-account filter. Either store and pass the returned
sub-account ID, or omit this filter when authenticating with a key already
scoped to the sub-account.

Official reference: [Search activity](https://developers.smtp2go.com/reference/search-activity).

## Proposed architecture

### 1. Add a provisioning service to RA Delivery

Add a provider-specific service under `com_ra_delivery`, for example:

```text
com_ra_delivery/site/src/Service/Smtp2goProvisioningService.php
```

RA Delivery, rather than RA Setup, should own SMTP2GO endpoints, payloads,
response parsing, and provider errors. RA Setup should orchestrate the wizard
only.

The service should receive or encapsulate:

- an injectable HTTP transport;
- the master API base URL and key;
- bounded connection and request timeouts;
- response validation and sanitised error reporting.

Suggested operations:

```php
findSubaccount(string $name): ?Smtp2goSubaccount;
createSubaccount(SubaccountRequest $request): Smtp2goSubaccount;
createApiKey(string $subaccountId, ApiKeyRequest $request): CreatedApiKey;
registerSenderDomain(string $subaccountId, string $hostname): SenderDomainResult;
```

`SmtpHelper` should expose the Step 7 validation and Step 8 provisioning entry
points required by RA Setup, while delegating provider requests to this service.

Introduce a shared lower-level SMTP2GO JSON client and migrate
`Smtp2goActivityService` to it separately or as part of the same RA Delivery
release. The client should accept an endpoint and payload, add authentication
headers, accept documented 2xx responses, decode JSON, retain `request_id` for
diagnostics, and throw a typed exception containing the HTTP status and
provider error code without exposing either API key.

### 2. Use and then repurpose the configured master record

Provisioning requires a master-account key with permission to search and create
sub-accounts, create API keys, and register sender domains. Load that key from
the enabled `#__ra_api_sites` record selected by the existing
`com_ra_delivery.smtp2go_api_site_id` parameter.

Before the Step 7 validation call, fail safely if the configured record is
absent, disabled, has an empty token, or uses an unsupported endpoint. Never
place the master key in a form, session, log, exception, or enqueued message.

After every provider operation succeeds, update this same record in place with
the generated sub-account key. This deliberately replaces the local copy of
the master token, as specified by the README. Preserve the endpoint, colour,
ordering, and other metadata. Because the master token will no longer be
available locally after success, all validation, creation, and registration
calls must finish before this update is committed.

The master key's permissions for all four required operations are confirmed.
No credential-restoration workflow is required: exceptional recovery is by a
knowledgeable administrator using SMTP2GO interactively or, after catastrophic
failure, by recloning the site and repeating setup.

### 3. Split validation and provisioning between Steps 7 and 8

Required decision flow:

1. Normalise and validate the submitted `sub_account` against SMTP2GO's
   documented naming rules. Surface validation details to the user.
2. Validate the selected delivery-notification user.
3. Locate the Webmaster user ID recorded by Steps 5 and 6, load that enabled
   user, and validate the user's current email address.
4. Load the configured master API-site record.
5. Search SMTP2GO for the proposed name. If any exact match exists, log and
   display the required collision message and retain the Step 7 form data.
6. If the name is available, save `smtp2go_subaccount_name = sub_account`
   transiently in RA Delivery configuration and save the selected
   delivery-notification user ID in `notify_user`.
7. Permit Step 7 to be repeated, replacing these captured values after another
   successful read-only validation.
8. Continue to Step 8 without creating any remote resource.
9. When Step 8 is confirmed, atomically check for and create the durable
   `#__ra_control.record_type = 3` one-shot guard before sending the first
   mutating SMTP2GO request. If the record already exists, throw an exception.
10. Create the sub-account using the saved name,
   current Webmaster email, and configured RA Setup email limit.
11. Capture and retain the returned unique SMTP2GO sub-account ID.
12. Create the restricted sub-account API key and immediately capture its
   unmasked key.
13. Register the canonical hostname from `com_ra_tools.website` as a sender
    domain for the new sub-account.
14. Write the returned DNS/CNAME setup details to `#__ra_log_file` with
    `sub_system = 'RA Setup'` and `record_type = 1`.
15. Only after all remote calls succeed, update the configured API-site row in
    one local database transaction.

The remote calls must not run while a long-lived database transaction is open.
SMTP2GO cannot participate in that transaction, and network waits would hold
database locks without providing atomicity.

### 4. Persist and activate the generated key

Within the local transaction:

1. Update the selected `#__ra_api_sites` record using Joomla's database query
   API and bound/quoted values.
2. Store the unmasked generated key only in `token`.
3. Set `title` to `Sub account name = <name>; sub account id = <SMTP2GO ID>`.
4. Preserve `url`, `colour`, ordering, and all other fields.
5. Store the returned SMTP2GO sub-account ID in the title and in a dedicated RA
   Delivery configuration value such as `smtp2go_subaccount_id`.
6. Keep `smtp2go_api_site_id` pointing to the same row; this configuration
   value is the sole source of truth for the local `#__ra_api_sites.id`.
7. Remove the transient `smtp2go_subaccount_name` value after the final update
   succeeds, and retain `notify_user` as the selected Joomla user ID.
8. Commit and then enqueue messages that identify the sub-account and local
   record ID, but never the API key.

After any partial remote failure, make best-effort compensating calls to revoke
the generated key, remove the sender-domain registration where appropriate,
and close the newly created sub-account. Log the success or failure of each
cleanup action to the system log. Never log either API key. Every failure
message must note that a knowledgeable administrator can recover the setup
interactively on the SMTP2GO website. If recovery is impractical, the site can
be recloned and configured again.

### 5. Integrate Steps 7 and 8

Update `SevenController::update()` to retain its CSRF, wizard-access,
form-validation, and Mailman-enabled checks, then delegate the availability
check to RA Delivery. Save accepted values, including the selected user ID in
`notify_user`, and retain submitted non-secret data after failure.

Do not add the `record_type = 3` completion check to Step 7. Update
`SevenModel::loadFormData()` to read the selected ID back from `notify_user` so
that the saved values can be reviewed and replaced on a later Step 7 run.
Change the RA Delivery `notify_user` configuration field from an email field to
a Joomla-user selection or validated integer field, and update `SmtpHelper` to
look up the current enabled user's email immediately before sending a
notification. No migration logic is needed.

Update `EightController::next()` or its model/application service so that final
confirmation first acquires the `record_type = 3` one-shot guard and then
triggers the three mutating provider calls and the local API-site update. Guard
creation must be atomic so concurrent Step 8 requests cannot both proceed.
Once any mutating request has been sent, retain the guard even after failure;
Step 8 must never be rerun where SMTP2GO was updated or the remote outcome is
uncertain. The guard may be released only for a failure conclusively occurring
before any mutating SMTP2GO request was sent. In guarded failure cases, keep the
captured data for diagnosis and direct the administrator to the manual recovery
route instead of returning an actionable Step 8 retry.

After all provisioning and local updates succeed, update the guard value with
the completion timestamp as part of `completeWizard()`. Step 7 does not inspect
this guard; the Step 8 entry point is solely responsible for enforcing it.

After successful completion, add this warning to the final confirmation
message: `The CNAME records for the new domain must now be updated.`

RA Setup must boot `com_ra_delivery` before resolving the SMTP2GO service and
must distinguish validation, configuration, provider, rate-limit, and local
persistence failures in user-facing messages. Do not place HTTP requests, raw
cURL calls, API keys, or SQL construction in either controller.

### 6. Dependency and release management

RA Setup must require the first RA Delivery version that exposes the
provisioning service. Extend the installer/preflight checks to verify the
minimum enabled versions of both `com_ra_delivery` and `com_ra_mailman` when
Mailman is selected.

No new provider-reference column is required. Store the provider sub-account ID
in the API-site title and RA Delivery configuration. Coordinate the required RA
Delivery and RA Tools logging capabilities before the first RA Setup release.

## Failure and retry behaviour

| Failure point | Required behaviour |
|---|---|
| `#__ra_control.record_type = 3` exists | Step 7 remains repeatable, but Step 8 throws an exception before any provider call |
| Master credential missing | Stop before remote calls; retain the Step 7 form |
| RA Setup email limit missing or invalid | Stop before creating the sub-account; retain the Step 7 and Step 8 state |
| Sub-account search fails or is ambiguous | Stop Step 7; show a sanitised error and request ID |
| Sub-account name already exists | Stop Step 7; log and display the required collision message |
| Sub-account creation fails | Do not update the API-site row or complete the wizard |
| API-key creation fails after sub-account creation | Keep the master row unchanged and attempt to close the sub-account |
| API key response is missing the unmasked key | Treat as failure; do not store a blank or masked token |
| Sender-domain registration fails | Keep the master row unchanged; attempt to revoke the key and close the sub-account |
| Local database write fails after all remote calls | Roll back local writes and attempt to remove the domain, revoke the key, and close the sub-account |
| HTTP 429 | Do not retry in a tight loop; show a retry-later message and respect provider limits |
| Any partial provisioning failure | Log each cleanup result and advise that an administrator can recover interactively in SMTP2GO |
| Repeated Step 7 | Permit another read-only validation and replace the captured Step 7 values |
| Repeated Step 8 after a mutating request was sent | Retain the `record_type = 3` guard and prohibit another wizard provisioning attempt |

The read-only Step 7 search may use bounded retries with exponential backoff and
jitter for transient connection or 5xx failures. Once Step 8 has sent a
mutating request, do not automatically retry that request or rerun Step 8. Keep
the guard and use the documented manual recovery route, because the remote
result may be incomplete or uncertain. A catastrophic failure may instead be
resolved by recloning the template and repeating the full setup.

## Testing plan

### Unit tests

Use a fake HTTP transport and fixture responses to cover:

- exact sub-account match, no match, and ambiguous match, including the required
  collision message;
- successful sub-account creation and ID extraction;
- successful API-key creation and immediate capture of the unmasked key;
- the configured RA Setup email limit in the sub-account payload;
- exact `/email/send` and `/activity/search` permissions and no administration
  permissions;
- sender-domain URL parsing, hostname canonicalisation, request construction,
  `subaccount_id`, and `data.domains` response parsing;
- Webmaster lookup from Steps 5/6 and use of the current email address as
  `subaccount_email`;
- disabled dedicated IP, archiving, 2FA enforcement, and SMS options;
- malformed JSON and missing `data`, sub-account ID, API key, or domain result;
- 400, 401, 402, 429, and 5xx responses;
- provider errors with and without `request_id`;
- proof that exception text and logs never contain either credential;
- Step 7 sub-account-name canonicalisation and invalid input;
- proof that Step 7 performs no create operation and Step 8 performs no search
  with the generated child credential;
- repeated Step 7 validation replaces the captured name and user without any
  mutating SMTP2GO request;
- the atomic `record_type = 3` guard prevents concurrent or repeated Step 8
  provisioning calls;
- every partial-failure message includes the interactive-administrator recovery
  note, and every cleanup result is logged without credentials.

### Database/integration tests

Cover:

- Step 7 stores the accepted name transiently in `smtp2go_subaccount_name` and
  stores the selected Joomla user ID in `notify_user`;
- RA Delivery resolves `notify_user` to the user's current email when sending a
  notification;
- the configured API-site row keeps the same ID and its `url`, colour, ordering,
  and unrelated fields remain unchanged;
- its `title` and `token` are replaced only after all provider calls succeed;
- the title contains the sub-account name and SMTP2GO sub-account ID, while
  `smtp2go_api_site_id` remains the sole source of the local API-site row ID;
- the provider sub-account ID is saved in RA Delivery configuration and is used
  for `/activity/search` filtering;
- the transient sub-account name is removed after successful final update;
- sender-domain response details are written to `#__ra_log_file` using
  `sub_system = 'RA Setup'` and `record_type = 1`;
- local rollback when parameter update fails;
- recovery after sub-account success followed by API-key failure;
- recovery after API-key success followed by sender-domain failure;
- attempts to change the configured website hostname after provisioning are
  rejected;
- a failure conclusively detected before any mutating request releases the
  guard and leaves Step 8 available;
- after any mutating request is sent, failure retains the guard and Step 8
  remains unavailable even if final wizard completion did not succeed;
- the final confirmation warns that the CNAME records must be updated;
- no migration paths are present in this unreleased component.

### Manual provider test

Use a non-production SMTP2GO master account or an agreed disposable namespace.
Verify the actual current response property names for the sub-account and key
calls, inspect the generated key permissions, register a disposable sender
domain, inspect the returned DNS setup records, send one test email, query
activity, test collision and partial-failure recovery, and then clean up all
remote test resources.

## Acceptance criteria

- Step 7 rejects an existing sub-account using the specified message and does
  not create any remote resource.
- Step 7 saves the accepted sub-account name and notification user ID, then
  continues to Step 8.
- Step 7 can be rerun to replace the captured sub-account name or
  notification/Webmaster details without modifying SMTP2GO; it never clears the
  Step 8 guard or re-enables provisioning.
- A valid Step 8 confirmation creates exactly one SMTP2GO sub-account using the
  configured RA Setup email limit and the current Webmaster email from Steps
  5/6; paid and optional features remain disabled.
- Exactly one usable sub-account API key is associated with the successful local
  configuration and it has only `/email/send` and `/activity/search`
  permissions.
- The hostname from `com_ra_tools.website` is registered for the new
  sub-account and returned DNS setup details are handled without claiming the
  domain is already verified.
- The full returned key is stored in `#__ra_api_sites.token` and is never shown
  or logged.
- The existing `com_ra_delivery.smtp2go_api_site_id` row is updated in place;
  its title begins `Sub account name = <name>` and also records the provider
  sub-account ID, while its endpoint, colour, ordering, and unrelated fields
  are unchanged.
- `com_ra_delivery.smtp2go_api_site_id` is the sole source of the local API-site
  row ID. The provider sub-account ID is separately stored in RA Delivery
  configuration and used to retrieve delivery exceptions.
- `notify_user` contains a Joomla user ID and RA Delivery resolves the current
  email address when a notification is sent.
- Email sending and activity polling work using the generated key.
- The wizard is completed only after provisioning and the local update succeed.
- Sender-domain DNS/CNAME details are logged to `#__ra_log_file` as RA Setup
  record type 1, and the final confirmation tells the administrator to update
  the new domain's CNAME records.
- Step 8 cannot be rerun after SMTP2GO has been updated or where the result of a
  mutating request is uncertain; the durable guard prevents duplicate remote
  resources.
- A partial failure is recoverable without losing the sub-account identity or
  exposing a credential; cleanup is attempted and its outcome logged.
- Provider and local errors retain Step 7 form data and present an actionable,
  sanitised message that mentions interactive SMTP2GO recovery.

## Remaining implementation input

The authenticated SMTP2GO response shapes for sub-account creation and API-key
creation will be supplied later. Do not finalise those DTO parsers until the
exact paths of the returned sub-account ID and unmasked API key have been
confirmed.

Step 7 name validation must follow SMTP2GO's current documented permitted
characters and constraints and return useful validation details. The value is a
sub-account name, not the website hostname. The hostname comes from the current
session value stored by Step 1 in `com_ra_tools.website` and is not changeable
after provisioning.

## Primary references

- [SMTP2GO authentication](https://developers.smtp2go.com/reference/authentication)
- [Add a subaccount](https://developers.smtp2go.com/reference/add-subaccount)
- [View subaccounts](https://developers.smtp2go.com/reference/search-subaccounts)
- [Add a new API key](https://developers.smtp2go.com/reference/add-api-key)
- [View API keys](https://developers.smtp2go.com/reference/view-api-keys)
- [Add a sender domain](https://developers.smtp2go.com/reference/add-sender-domain)
- [Search activity](https://developers.smtp2go.com/reference/search-activity)
- [SMTP2GO API changelog](https://developers.smtp2go.com/reference/changelog)
