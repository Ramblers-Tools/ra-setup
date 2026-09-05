# com_ra_setup

## Purpose

com_ra_setup is a Joomla component that provides an installation wizard designed to prepare a template site for production as a website for an individual Group.

It comprises a series of steps to guide an initial end-user through a number of screens to record their requirements, and configures as required a number of different modules, menus and components. It also prompts for the names and roles of key members of the Group, creates User records and defines their permitted access permissions. 

The individual steps are as follows:

- the site identity and web-site header,
- the home page content, including an optional link to Facebook
- walk programme defaults,
- optional components,
- committee member names,
- committe member roles
- email configuration.

## Scope

The component provides:

- a front-end wizard flow with step-by-step views,
- an administrative menu that allows certain of the setup steps to be repeated,
- integration with other components from Ramblers Tools (com_ra_events, com_ra_treasurer, com_ra_mailman, com_ra_delivery and com_ra_sso),
- customisation of access permissions using the standard Jooml Accecc Control Levels.

## High-level architecture

- Front-end views for the wizard steps.
- A single admin view for re-running steps.
- Shared helper logic in site/src/Helper/SetupHelper.php.
- Use of ToolsHelper functions for database access and configuration updates.
- Use of Joomla user state for temporary data across multi-step workflows.

## Email configuration

RA Setup has not yet been released, so this design does not require migration
or backward-compatibility handling.

The wizard provisions a new website cloned from the private template site. The
clone contains a local copy of the SMTP2GO master API key. It is safe to replace
that local copy with the new sub-account key after provisioning; the template
retains its master key. In a catastrophic failure, the new site can be cloned
again and the entire configuration repeated.

All wizard operations are also available from the Joomla back end to a
knowledgeable administrator. The wizard should nevertheless complete as much
of the process as possible without manual intervention.

### Step 7: sub-account configuration

Use the term **Sub account** consistently in Step 7. The form field currently
called `domain` should become `sub_account` before release. SMTP2GO's documented
character rules must be applied locally, and the SMTP2GO validation response
must highlight any remaining problem. The website hostname is separate and
typically begins with the Group name, for example
`hinckleyramblers.org.uk`.

Step 7 uses functions exposed by RA Delivery's `SmtpHelper` to search for an
existing SMTP2GO sub-account with the proposed name. If one exists, log and
display:

```text
Unable to register sub-account {sub-account} as it already exists
```

If validation succeeds, retain the sub-account name transiently in RA Delivery
configuration until Step 8 completes. Store the selected delivery-notification
Joomla user ID in `notify_user`.

Step 7 is repeatable because it only validates and captures local configuration
and performs a read-only availability check. It may be rerun, for example to
correct the proposed sub-account name or change the Webmaster. It must not
create or modify any SMTP2GO resource. If the Step 8 guard already exists, a
Step 7 save must neither clear that guard nor make Step 8 available again.

The Webmaster identified in Steps 5 and 6 supplies the SMTP2GO sub-account
contact. Look up that user's current email address and use it as
`subaccount_email`; do not store a copied email value as the authoritative
Webmaster identity.

### Step 8: final provisioning

Step 8 is irreversible from the wizard. Before provisioning, check
`#__ra_control` for `record_type = 3` and throw an exception if it exists. Use
that record as a durable one-shot guard so that Step 8 cannot be invoked again
after SMTP2GO has been updated, including after a partial provisioning failure
or an uncertain remote response. Step 7 must not apply this guard.

After final confirmation and acquiring the guard, load the master API key from
the `#__ra_api_sites` record selected by RA Delivery and perform these
operations:

1. Create the sub-account using its validated name, the Webmaster's email, and
   the email limit in RA Setup configuration. Dedicated IP, archiving, 2FA
   enforcement, and SMS must remain disabled.
2. Create a sub-account API key restricted to `/email/send` and
   `/activity/search`.
3. Register the hostname stored by Step 1 in `com_ra_tools.website` as an
   SMTP2GO sender domain for the new sub-account.
4. Update the existing local API-site record only after all remote operations
   succeed.

The API-site title must identify both the sub-account name and the SMTP2GO
sub-account ID, beginning `Sub account name = <name>`. Store the returned API
key in plaintext in `token`; only Superusers may see these records. Preserve
the endpoint, colours, ordering, and other fields. RA Delivery's
`smtp2go_api_site_id` remains the sole source of truth for the local
`#__ra_api_sites.id` value.

Persist the returned SMTP2GO sub-account ID in RA Delivery configuration and
use that ID—not the sub-account name—when filtering `/activity/search` for
delivery exceptions. Remove the transient configuration copy of the
sub-account name after the final update succeeds.

Store the sender-domain response, including the CNAME/DNS setup details, in
`#__ra_log_file` with `sub_system = 'RA Setup'` and `record_type = 1`. The final
confirmation message must advise that the CNAME records for the new domain
still need to be updated manually.

On partial failure, make a best effort to revoke the generated key, remove the
sender-domain registration where appropriate, and close the new sub-account.
Log the success or failure of every cleanup action to the system log without
logging either API key. Every provisioning failure message must also state that
a knowledgeable administrator can recover the configuration interactively on
the SMTP2GO website. If recovery is impractical, the site can be recloned and
the complete wizard run again.

## The deployment method is as follows

 - The template site is initially private (https://template.ramblers.tools) and all possible software is installed on it. Two mailing lists are present, and Superuser records are present for support staff.

 - On request, this is copied to a production domain, typically xxxramblers.org.uk

 - High level configuration of backups etc. is carried out by RamblersWebs, and cron jobs are created:
   - ra_mailman:sendemails (every 15 minutes)
   - ra_delivery:pollactivity (daily, soon after midnight)
   - ra_events:eventscopy (daily)
   - ra_members:loadusers (daily)

 - The end user is notified of availability, and advised to immediately configure the site.

 - When the front end of the site is visited, the Wizard is presented. (It is theoretically possible that a bad actor may access the site first, and maliciously configure it. However, this very low risk is accepted in order to keep the process as simple as possible).

 - When the configuration has been completed:

1. The Wizard is disabled.

2. The new front page is enabled

3. Confirmation emails are sent to the support staff (with details of the components selected). RamblersWebs will then be responsible for enabling the required batch jobs.

4. The website is available for public use, showing the chosen front page, the list of Committee members and the appropriate menus. 

5. The webmaster or the Membership Secretary will be able to access the back end of the website and import members from the Insight Hub to populate the mailing list "Members newsletter"

6. The designated Authors will be able to create Mailshots from the front end.

7. The designated members will be able to create Events from the front end.

8. An email is sent to the newly designated webmaster, and (s)he is responsible for notifying/training the other committee members.
