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

## The deployment method is as follows

 - The template site is initially private (https://template.ramblers.tools) and all possible software is installed on it. Two mailing lists are present, and Superuser records are present for support staff.

 - On request, this is copied to a production domain, typically xxxramblers.org.uk

 - High level configuration of backups etc. is carried out by RamblersWebs, and cron jobs are created:
   - ra_mailman:sendemails 
   - ra_delivery:pollactivity 
   - ra_events:eventscopy 
   - ra_members:loadusers

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
